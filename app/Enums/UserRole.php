<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Reviewer = 'reviewer';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Admin->value => 'Administrator',
            self::Editor->value => 'Editor',
            self::Reviewer->value => 'Reviewer',
        ];
    }
}
