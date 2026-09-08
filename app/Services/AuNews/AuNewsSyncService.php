<?php

namespace App\Services\AuNews;

use App\Models\NewsItem;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuNewsSyncService
{
    public function __construct(private AuNewsContentSanitizer $sanitizer)
    {
    }

    public function syncArticle(string $url, array $data): string
    {
        $title = Str::squish((string) ($data['title'] ?? ''));
        $body = $this->sanitizer->sanitize($data['body'] ?? null);
        $excerpt = $data['excerpt'] ?? null;

        if ($excerpt === null || trim($excerpt) === '') {
            $excerpt = $this->sanitizer->makeExcerpt($body, 240);
        }

        $contentHash = hash('sha256', $title.'|'.strip_tags($body ?? ''));
        $scrapedAt = now();
        $item = NewsItem::where('source_url', $url)->first();

        if ($item !== null && $item->content_hash === $contentHash) {
            NewsItem::withoutTimestamps(function () use ($item, $scrapedAt): void {
                $item->update(['last_scraped_at' => $scrapedAt]);
            });

            return 'skipped';
        }

        if ($item !== null && $item->sync_mode === 'local') {
            $item->update([
                'source_changed_at' => $scrapedAt,
                'last_scraped_at' => $scrapedAt,
            ]);

            return 'changed';
        }

        $fields = [
            'type' => trim((string) ($data['type'] ?? '')) ?: 'news',
            'title' => $title,
            'excerpt' => $excerpt,
            'body' => $body,
            'image_url' => $data['image_url'] ?? null,
            'published_at' => $this->parseDate($data['published_at'] ?? null),
            'locale' => trim((string) ($data['locale'] ?? '')) ?: 'en',
            'content_hash' => $contentHash,
            'last_scraped_at' => $scrapedAt,
            'metadata' => $data['metadata'] ?? null,
        ];

        if ($item === null) {
            NewsItem::create(array_merge($fields, [
                'slug' => $this->uniqueSlug($title),
                'source_url' => $url,
                'source_domain' => 'au.int',
                'status' => config('au-news.default_status', 'review'),
                'sync_mode' => 'source',
            ]));

            return 'created';
        }

        if ($item->title !== $title && trim((string) $item->slug) === '') {
            $fields['slug'] = $this->uniqueSlug($title);
        }

        $fields['source_changed_at'] = null;
        $item->update($fields);

        return 'updated';
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'news';
        $slug = $base;
        $suffix = 2;

        while (NewsItem::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
