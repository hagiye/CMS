<?php

namespace App\Enums;

enum ContentNodeType: string
{
    case Edition = 'edition';
    case Section = 'section';
    case Chapter = 'chapter';
    case Article = 'article';
    case Page = 'page';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Edition->value => 'Handbook edition',
            self::Section->value => 'Section',
            self::Chapter->value => 'Chapter',
            self::Article->value => 'Article',
            self::Page->value => 'Page',
        ];
    }

    public function label(): string
    {
        return self::options()[$this->value];
    }

    public function parentType(): ?self
    {
        return match ($this) {
            self::Edition => null,
            self::Section => self::Edition,
            self::Chapter => self::Section,
            self::Article, self::Page => self::Chapter,
        };
    }

    /**
     * @return array<string, string>
     */
    public function childOptions(): array
    {
        return match ($this) {
            self::Edition => [self::Section->value => self::Section->label()],
            self::Section => [self::Chapter->value => self::Chapter->label()],
            self::Chapter => [
                self::Article->value => self::Article->label(),
                self::Page->value => self::Page->label(),
            ],
            self::Article, self::Page => [],
        };
    }
}
