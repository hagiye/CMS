<?php

namespace App\Http\Resources;

use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Support\LocalePreference;
use Illuminate\Http\Request;

class SearchResultResource extends ContentNodeResource
{
    private const EXCERPT_LENGTH = 180;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->relationLoaded('searchTranslation')
            ? $this->searchTranslation
            : LocalePreference::select($this->translations, $request->query('locale'));
        $term = trim((string) $request->query('q'));
        $field = $this->matchedField($translation, $term);

        return [
            ...parent::toArray($request),
            'match' => [
                'locale' => $translation?->locale,
                'field' => $field,
                'excerpt' => $this->excerpt($translation, $term, $field),
            ],
            'breadcrumbs' => $this->breadcrumbs($request),
        ];
    }

    private function matchedField(?ContentTranslation $translation, string $term): ?string
    {
        if ($translation === null || $term === '') {
            return null;
        }

        if (mb_stripos($this->plainText($translation->title), $term) !== false) {
            return 'title';
        }

        if (mb_stripos($this->plainText($translation->body), $term) !== false) {
            return 'body';
        }

        return 'content';
    }

    private function excerpt(?ContentTranslation $translation, string $term, ?string $field): ?string
    {
        if ($translation === null) {
            return null;
        }

        $text = $field === 'title'
            ? $this->plainText($translation->title)
            : $this->plainText($translation->body);

        if ($text === '') {
            $text = $this->plainText($translation->title);
        }

        if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
            return $text;
        }

        $matchPosition = $term === '' ? false : mb_stripos($text, $term);
        $start = $matchPosition === false ? 0 : max(0, $matchPosition - 60);
        $excerpt = mb_substr($text, $start, self::EXCERPT_LENGTH);

        if ($start > 0 && ($firstSpace = mb_strpos($excerpt, ' ')) !== false) {
            $excerpt = mb_substr($excerpt, $firstSpace + 1);
        }

        return ($start > 0 ? '…' : '').rtrim($excerpt).(mb_strlen($text) > $start + self::EXCERPT_LENGTH ? '…' : '');
    }

    private function plainText(?string $value): string
    {
        $value = html_entity_decode(strip_tags($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * @return array<int, array{slug: string, type: string, title: ?string, locale: ?string}>
     */
    private function breadcrumbs(Request $request): array
    {
        $nodes = collect([$this->resource]);
        $parent = $this->relationLoaded('publicParent') ? $this->publicParent : null;

        while ($parent instanceof ContentNode) {
            $nodes->push($parent);
            $parent = $parent->relationLoaded('publicParent') ? $parent->publicParent : null;
        }

        return $nodes->reverse()->values()->map(function (ContentNode $node) use ($request): array {
            $translation = $node->relationLoaded('translations')
                ? LocalePreference::select($node->translations, $request->query('locale'))
                : null;

            return [
                'slug' => $node->slug,
                'type' => $node->type,
                'title' => $translation?->title,
                'locale' => $translation?->locale,
            ];
        })->all();
    }
}
