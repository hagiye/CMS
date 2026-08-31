<?php

namespace App\Models;

use App\Support\LocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

class ContentTranslation extends Model
{
    use HasFactory, Searchable;

    protected $fillable = ['content_node_id', 'locale', 'title', 'body'];

    public function setLocaleAttribute(string $locale): void
    {
        $this->attributes['locale'] = LocalePreference::normalize($locale);
    }

    #[SearchUsingFullText(['title', 'body'])]
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->node?->isPublished() ?? false;
    }

    /**
     * Select one searchable translation per node using the locale fallback order.
     *
     * @param  array<int, string>  $locales
     */
    public function scopePreferredForLocales(Builder $query, array $locales): Builder
    {
        return $query->where(function (Builder $translations) use ($locales) {
            foreach (array_values($locales) as $position => $locale) {
                $higherPriorityLocales = array_slice($locales, 0, $position);

                $translations->orWhere(function (Builder $candidate) use ($locale, $higherPriorityLocales) {
                    $candidate->where('locale', $locale);

                    if ($higherPriorityLocales !== []) {
                        $candidate->whereNotExists(function ($preferred) use ($higherPriorityLocales) {
                            $preferred->selectRaw('1')
                                ->from('content_translations as preferred_translations')
                                ->whereColumn(
                                    'preferred_translations.content_node_id',
                                    'content_translations.content_node_id',
                                )
                                ->whereIn('preferred_translations.locale', $higherPriorityLocales);
                        });
                    }
                });
            }
        });
    }

    public function node()
    {
        return $this->belongsTo(ContentNode::class, 'content_node_id');
    }
}
