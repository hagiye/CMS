<?php

namespace App\Jobs;

use App\Models\NewsImportRun;
use App\Services\AuNews\AuNewsClient;
use App\Services\AuNews\AuNewsListingParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ImportAuNews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AuNewsClient $client, AuNewsListingParser $parser): void
    {
        $maxPages = max(1, (int) config('au-news.max_pages', 5));
        $listingUrls = [];

        $run = NewsImportRun::create([
            'source_url' => config('au-news.listing_url'),
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'pages_scanned' => 0,
                'max_pages' => $maxPages,
                'listing_urls' => [],
            ],
        ]);

        try {
            $url = $run->source_url;
            $visited = [];
            $items = [];

            for ($page = 0; $page < $maxPages && $url !== null; $page++) {
                if (isset($visited[$url])) {
                    break;
                }

                $visited[$url] = true;
                $html = $client->get($url);

                foreach ($parser->parse($html) as $item) {
                    $items[$item->url] ??= $item;
                }

                $listingUrls[] = $url;
                $run->update([
                    'items_found' => count($items),
                    'metadata' => array_merge($run->metadata ?? [], [
                        'pages_scanned' => count($listingUrls),
                        'max_pages' => $maxPages,
                        'listing_urls' => $listingUrls,
                    ]),
                ]);

                $url = $parser->nextPageUrl($html);
            }

            $run->update(['items_found' => count($items)]);

            foreach ($items as $item) {
                ImportAuNewsArticle::dispatch($item->url, [
                    'title' => $item->title,
                    'type' => $item->type,
                    'excerpt' => $item->excerpt,
                    'published_at' => $item->publishedDate,
                    'locale' => 'en',
                ], $run->id);
            }

            $run->update([
                'status' => 'dispatched',
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
