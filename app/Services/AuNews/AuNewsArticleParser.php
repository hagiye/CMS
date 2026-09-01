<?php

namespace App\Services\AuNews;

use Symfony\Component\DomCrawler\Crawler;

class AuNewsArticleParser
{
    public function parse(string $html, string $url): array
    {
        $crawler = new Crawler($html);

        return [
            'title' => $this->firstText($crawler, [
                'h1',
                '.page-header h1',
                '.node-title',
            ]),
            'body' => $this->bodyHtml($crawler),
            'image_url' => $this->imageUrl($crawler),
            'published_at' => $this->publishedAt($crawler),
            'metadata' => [
                'source_url' => $url,
            ],
        ];
    }

    private function bodyHtml(Crawler $crawler): ?string
    {
        $article = $crawler->filter('main article')->first();
        $scope = $article->count() > 0 ? $article : $this->contentScope($crawler);

        foreach ([
            '.field--name-body',
            '.field-name-body',
            'article .content',
            'main article',
        ] as $selector) {
            if ($selector === 'article .content' && $article->count() > 0) {
                $match = $article->filter('.content')->first();
            } elseif ($selector === 'main article') {
                $match = $article;
            } else {
                $match = $scope->filter($selector)->first();
            }

            if ($match->count() === 0) {
                continue;
            }

            if (in_array($selector, ['.field--name-body', '.field-name-body'], true)) {
                $fieldItem = $match->filter('.field__item')->first();

                if ($fieldItem->count() > 0) {
                    $match = $fieldItem;
                }
            }

            $html = trim($match->html());

            if ($html !== '') {
                return $html;
            }
        }

        return null;
    }

    private function imageUrl(Crawler $crawler): ?string
    {
        foreach ([
            'meta[property="og:image"]',
            'article img',
        ] as $selector) {
            $match = $crawler->filter($selector)->first();

            if ($match->count() === 0) {
                continue;
            }

            $imageUrl = $match->nodeName() === 'meta'
                ? $match->attr('content')
                : ($match->attr('data-src') ?: $match->attr('src'));

            if ($imageUrl !== null && trim($imageUrl) !== '') {
                return $this->absoluteUrl($imageUrl);
            }
        }

        return null;
    }

    private function publishedAt(Crawler $crawler): ?string
    {
        $meta = $crawler->filter('meta[property="article:published_time"]')->first();

        if ($meta->count() > 0) {
            $publishedAt = trim((string) $meta->attr('content'));

            if ($publishedAt !== '') {
                return $publishedAt;
            }
        }

        $scope = $this->contentScope($crawler);

        foreach (['time', '.date'] as $selector) {
            $match = $scope->filter($selector)->first();

            if ($match->count() === 0) {
                continue;
            }

            $publishedAt = $selector === 'time'
                ? trim((string) ($match->attr('datetime') ?: $match->text('', true)))
                : trim($match->text('', true));

            if ($publishedAt !== '') {
                return $publishedAt;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $selectors
     */
    private function firstText(Crawler $crawler, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $match = $crawler->filter($selector)->first();

            if ($match->count() === 0) {
                continue;
            }

            $text = trim($match->text('', true));

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function contentScope(Crawler $crawler): Crawler
    {
        foreach (['main article', 'main'] as $selector) {
            $scope = $crawler->filter($selector)->first();

            if ($scope->count() > 0) {
                return $scope;
            }
        }

        return $crawler;
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim($url);
        $baseUrl = rtrim((string) config('au-news.base_url'), '/');

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }

        if (parse_url($url, PHP_URL_SCHEME) !== null) {
            return $url;
        }

        return $baseUrl.'/'.ltrim($url, '/');
    }
}
