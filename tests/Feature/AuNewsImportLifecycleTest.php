<?php

namespace Tests\Feature;

use App\Data\AuNewsListingItem;
use App\Jobs\ImportAuNews;
use App\Jobs\ImportAuNewsArticle;
use App\Models\NewsImportRun;
use App\Services\AuNews\AuNewsArticleParser;
use App\Services\AuNews\AuNewsClient;
use App\Services\AuNews\AuNewsListingParser;
use App\Services\AuNews\AuNewsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AuNewsImportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_dispatches_unique_articles_and_waits_for_completion(): void
    {
        Queue::fake();
        $this->dispatchListing();
        Queue::assertPushed(ImportAuNewsArticle::class, 1);
        $run = NewsImportRun::sole();
        $this->assertEquals(1, $run->items_found);
        $this->assertSame('processing', $run->status);
        $this->assertNull($run->finished_at);
    }

    public function test_parent_completes_when_articles_finish_during_dispatch(): void
    {
        $client = Mockery::mock(AuNewsClient::class);
        $client->shouldReceive('get')->once()->andReturn('article');
        $parser = Mockery::mock(AuNewsArticleParser::class);
        $parser->shouldReceive('parse')->once()->andReturn([]);
        $sync = Mockery::mock(AuNewsSyncService::class);
        $sync->shouldReceive('syncArticle')->once()->andReturn('created');
        $this->app->instance(AuNewsClient::class, $client);
        $this->app->instance(AuNewsArticleParser::class, $parser);
        $this->app->instance(AuNewsSyncService::class, $sync);
        $this->dispatchListing();
        $run = NewsImportRun::sole();
        $this->assertSame('completed', $run->status);
        $this->assertEquals(1, $run->items_created);
        $this->assertNotNull($run->finished_at);
    }

    private function dispatchListing(): void
    {
        $item = new AuNewsListingItem('Article', 'https://au.int/en/article', null, null, null);
        $client = Mockery::mock(AuNewsClient::class);
        $client->shouldReceive('get')->once()->andReturn('listing');
        $parser = Mockery::mock(AuNewsListingParser::class);
        $parser->shouldReceive('parse')->once()->andReturn([$item, $item]);
        $parser->shouldReceive('nextPageUrl')->once()->andReturnNull();
        (new ImportAuNews)->handle($client, $parser);
    }

    public function test_article_results_complete_only_after_all_items_are_processed(): void
    {
        $run = NewsImportRun::create([
            'source_url' => 'https://au.int/en/news', 'status' => 'processing',
            'started_at' => now(), 'items_found' => 4,
        ]);

        foreach (['created', 'updated', 'skipped'] as $result) {
            $this->processArticle($run, $result);
            $this->assertSame('processing', $run->fresh()->status);
            $this->assertNull($run->fresh()->finished_at);
        }

        $this->processArticle($run, 'created');
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertEquals(2, $run->items_created);
        $this->assertEquals(1, $run->items_updated);
        $this->assertEquals(1, $run->items_skipped);
    }

    public function test_failed_article_completes_run_with_errors(): void
    {
        $run = NewsImportRun::create([
            'source_url' => 'https://au.int/en/news', 'status' => 'processing',
            'started_at' => now(), 'items_found' => 1,
        ]);
        $client = Mockery::mock(AuNewsClient::class);
        $client->shouldReceive('get')->once()->andThrow(new RuntimeException('Article failed'));

        try {
            (new ImportAuNewsArticle('https://au.int/en/article', [], $run->id))->handle(
                $client, Mockery::mock(AuNewsArticleParser::class), Mockery::mock(AuNewsSyncService::class),
            );
            $this->fail('Expected article failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Article failed', $exception->getMessage());
        }

        $this->assertSame('completed_with_errors', $run->fresh()->status);
        $this->assertEquals(1, $run->fresh()->items_failed);
        $this->assertNotNull($run->fresh()->finished_at);
    }

    public function test_articles_do_not_complete_a_run_while_parent_is_dispatching(): void
    {
        $run = NewsImportRun::create([
            'source_url' => 'https://au.int/en/news', 'status' => 'running',
            'started_at' => now(), 'items_found' => 1,
        ]);
        $this->processArticle($run, 'created');
        $this->assertSame('running', $run->fresh()->status);
        $this->assertNull($run->fresh()->finished_at);
    }

    public function test_empty_listing_completes(): void
    {
        $client = Mockery::mock(AuNewsClient::class);
        $client->shouldReceive('get')->once()->andReturn('listing');
        $parser = Mockery::mock(AuNewsListingParser::class);
        $parser->shouldReceive('parse')->once()->andReturn([]);
        $parser->shouldReceive('nextPageUrl')->once()->andReturnNull();
        (new ImportAuNews)->handle($client, $parser);
        $run = NewsImportRun::sole();
        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->finished_at);
    }

    public function test_listing_failure_marks_parent_failed(): void
    {
        $client = Mockery::mock(AuNewsClient::class);
        $client->shouldReceive('get')->once()->andThrow(new RuntimeException('Listing failed'));
        try {
            (new ImportAuNews)->handle($client, Mockery::mock(AuNewsListingParser::class));
            $this->fail('Expected listing failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Listing failed', $exception->getMessage());
        }
        $run = NewsImportRun::sole();
        $this->assertSame('failed', $run->status);
        $this->assertSame('Listing failed', $run->error_message);
        $this->assertNotNull($run->finished_at);
    }

    private function processArticle(NewsImportRun $run, string $result): void
    {
        $client = Mockery::mock(AuNewsClient::class);
        $client->shouldReceive('get')->once()->andReturn('article');
        $parser = Mockery::mock(AuNewsArticleParser::class);
        $parser->shouldReceive('parse')->once()->andReturn([]);
        $sync = Mockery::mock(AuNewsSyncService::class);
        $sync->shouldReceive('syncArticle')->once()->andReturn($result);
        (new ImportAuNewsArticle('https://au.int/en/article', [], $run->id))->handle($client, $parser, $sync);
    }
}
