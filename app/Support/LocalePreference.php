<?php

namespace App\Support;

use App\Models\ContentTranslation;
use Illuminate\Support\Collection;

final class LocalePreference
{
    public static function normalize(?string $locale): string
    {
        $parts = preg_split('/[-_]/', trim((string) $locale), 2);
        $language = strtolower($parts[0] ?: (string) config('app.locale', 'en'));

        return isset($parts[1]) && $parts[1] !== ''
            ? $language.'-'.strtoupper($parts[1])
            : $language;
    }

    /**
     * @return array<int, string>
     */
    public static function fallbacks(?string $locale): array
    {
        $locale = self::normalize($locale);
        $language = explode('-', $locale, 2)[0];

        return array_values(array_unique([
            $locale,
            $language,
            self::normalize((string) config('app.fallback_locale', 'en')),
        ]));
    }

    /**
     * @param  Collection<int, ContentTranslation>  $translations
     */
    public static function select(Collection $translations, ?string $locale): ?ContentTranslation
    {
        foreach (self::fallbacks($locale) as $preferredLocale) {
            $translation = $translations->first(
                fn (ContentTranslation $translation): bool => self::normalize($translation->locale) === $preferredLocale,
            );

            if ($translation !== null) {
                return $translation;
            }
        }

        return $translations
            ->sortBy(fn (ContentTranslation $translation): string => self::normalize($translation->locale))
            ->first();
    }
}
