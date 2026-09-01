<?php

namespace App\Services\AuNews;

use App\Data\AuNewsListingItem;
use DOMElement;
use Symfony\Component\DomCrawler\Crawler;

class AuNewsListingParser
{
    /**
     * @return array<int, AuNewsListingItem>
     */
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $items = [];

        $crawler->filter('a[href]')->each(function (Crawler $link) use (&$items): void {
            $url = $this->absoluteUrl((string) $link->attr('href'));

            if (
                $url === ''
                || ! str_starts_with($url, 'https://au.int/en/')
                || ! $this->looksLikeNewsLink($url)
                || isset($items[$url])
            ) {
                return;
            }

            $title = trim($link->text('', true));

            if ($title === '') {
                $title = trim((string) $link->attr('title'));
            }

            if ($title === '') {
                $image = $link->filter('img[alt]')->first();
                $title = $image->count() > 0 ? trim((string) $image->attr('alt')) : '';
            }

            if ($title === '') {
                return;
            }

            $container = $this->listingContainer($link);
            $type = $this->firstText($container, [
                '.field--name-field-type',
                '.field-name-field-type',
                '.views-field-field-type',
                '.views-field-type',
                '.content-type',
                '.news-type',
                '.category',
                'a[href*="/happening/"]',
            ]);
            $excerpt = $this->firstText($container, [
                '.field--name-body',
                '.field-name-body',
                '.views-field-body .field-content',
                '.views-field-body',
                '.summary',
                '.excerpt',
                'p',
            ]);

            if ($excerpt === $title) {
                $excerpt = null;
            }

            $items[$url] = new AuNewsListingItem(
                title: $title,
                url: $url,
                type: $this->normalizeType($type, $url),
                excerpt: $excerpt,
                publishedDate: $this->publicationDate($container),
            );
        });

        return array_values($items);
    }

    private function looksLikeNewsLink(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        foreach ([
            '/pressreleases/',
            '/speeches/',
            '/readouts/',
            '/events/',
            '/newsevents/',
            '/statements/',
        ] as $newsPath) {
            if (str_contains($path, $newsPath)) {
                return true;
            }
        }

        return false;
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#')) {
            return '';
        }

        $baseUrl = rtrim((string) config('au-news.base_url'), '/');

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $url = $scheme.':'.$url;
        } elseif (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $url = $baseUrl.'/'.ltrim($url, '/');
        }

        $fragmentPosition = strpos($url, '#');

        return $fragmentPosition === false ? $url : substr($url, 0, $fragmentPosition);
    }

    private function normalizeType(?string $type, string $url): string
    {
        if ($type === null) {
            return $this->typeFromUrl($url) ?? 'news';
        }

        $type = strtolower(str_replace(['-', '_'], ' ', (string) $type));

        if (str_contains($type, 'media advisory') || str_contains($type, 'briefing')) {
            return 'media_advisory';
        }

        if (str_contains($type, 'press release') || str_contains($type, 'pressreleases')) {
            return 'press_release';
        }

        if (str_contains($type, 'speech')) {
            return 'speech';
        }

        if (str_contains($type, 'readout')) {
            return 'readout';
        }

        if (str_contains($type, '/events/') || trim($type) === 'event') {
            return 'event';
        }

        if (str_contains($type, 'statement')) {
            return 'statement';
        }

        return 'news';
    }

    private function typeFromUrl(string $url): ?string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        foreach ([
            '/pressreleases/' => 'press_release',
            '/speeches/' => 'speech',
            '/readouts/' => 'readout',
            '/events/' => 'event',
            '/newsevents/' => 'event',
            '/statements/' => 'statement',
        ] as $newsPath => $type) {
            if (str_contains($path, $newsPath)) {
                return $type;
            }
        }

        return null;
    }

    private function listingContainer(Crawler $link): Crawler
    {
        $node = $link->getNode(0)?->parentNode;
        $fallback = $node;

        while ($node instanceof DOMElement) {
            $tagName = strtolower($node->tagName);
            $className = strtolower($node->getAttribute('class'));

            if (
                in_array($tagName, ['article', 'li'], true)
                || str_contains($className, 'views-row')
                || str_contains($className, 'view-row')
                || str_contains($className, 'news-item')
                || str_contains($className, 'event-item')
                || str_contains($className, 'listing-item')
                || str_contains($className, 'card')
            ) {
                return new Crawler($node);
            }

            $node = $node->parentNode;
        }

        return $fallback instanceof DOMElement ? new Crawler($fallback) : $link;
    }

    /**
     * @param  array<int, string>  $selectors
     */
    private function firstText(Crawler $container, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $match = $container->filter($selector)->first();

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

    private function publicationDate(Crawler $container): ?string
    {
        $time = $container->filter('time')->first();

        if ($time->count() > 0) {
            $date = trim((string) ($time->attr('datetime') ?: $time->text('', true)));

            if ($date !== '') {
                return $date;
            }
        }

        return $this->firstText($container, [
            '.field--name-field-date',
            '.field-name-field-date',
            '.views-field-created',
            '.views-field-field-date',
            '.published-date',
            '.date-display-single',
            '.date',
        ]);
    }
}
