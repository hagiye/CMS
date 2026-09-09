<?php

namespace App\Jobs;

use App\Models\NewsImportRun;
use App\Services\AuNews\AuNewsArticleParser;
use App\Services\AuNews\AuNewsClient;
use App\Services\AuNews\AuNewsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportAuNewsArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public array $listingData,
        public int $runId,
    ) {
        $this->onQueue('au-news');
    }

    public function handle(
        AuNewsClient $client,
        AuNewsArticleParser $parser,
        AuNewsSyncService $syncService,
    ): void {
        try {
            $html = $client->get($this->url);
            $articleData = $parser->parse($html, $this->url);
            $data = array_merge($this->listingData, array_filter(
                $articleData,
                static fn ($value) => $value !== null,
            ));

            $result = $syncService->syncArticle($this->url, $data);
            $counter = match ($result) {
                'created' => 'items_created',
                'updated' => 'items_updated',
                'skipped', 'changed' => 'items_skipped',
            };
        } catch (Throwable $exception) {
            $this->recordResult('items_failed');
            report($exception);

            throw $exception;
        }

        $this->recordResult($counter);
    }

    private function recordResult(string $counter): void
    {
        DB::transaction(function () use ($counter): void {
            $run = NewsImportRun::whereKey($this->runId)->lockForUpdate()->firstOrFail();
            $run->increment($counter);
            $run->refresh();
            $run->completeIfProcessed();
            $run->save();
        });
    }
}
