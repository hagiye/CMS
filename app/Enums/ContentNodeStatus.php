<?php

namespace App\Enums;

enum ContentNodeStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Draft->value => 'Draft',
            self::Review->value => 'In review',
            self::Published->value => 'Published',
            self::Archived->value => 'Archived',
        ];
    }

    public function label(): string
    {
        return self::options()[$this->value];
    }
}
