<?php

namespace App\Services\AuNews;

use Mews\Purifier\Facades\Purifier;

class AuNewsContentSanitizer
{
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(Purifier::clean($html, [
            'HTML.Allowed' => 'p,br,strong,em,b,i,ul,ol,li,blockquote,h2,h3,h4,a[href|title|target|rel]',
            'Attr.AllowedFrameTargets' => ['_blank', '_self', '_parent', '_top'],
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
        ]));

        return $clean === '' ? null : $clean;
    }

    public function makeExcerpt(?string $html, int $length = 240): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Keep adjacent paragraphs and list items from running together.
        $html = preg_replace('/<\/?(?:p|br|div|li|ul|ol|blockquote|h[1-6])\b[^>]*>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? '');

        if ($text === '') {
            return null;
        }

        if ($length <= 0) {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        $excerpt = mb_substr($text, 0, $length, 'UTF-8');

        if (mb_substr($text, $length, 1, 'UTF-8') !== ' ') {
            $lastSpace = mb_strrpos($excerpt, ' ', 0, 'UTF-8');

            if ($lastSpace !== false) {
                $excerpt = mb_substr($excerpt, 0, $lastSpace, 'UTF-8');
            }
        }

        return rtrim($excerpt);
    }
}
