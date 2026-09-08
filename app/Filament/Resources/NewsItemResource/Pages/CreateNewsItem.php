<?php

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use App\Services\AuNews\AuNewsContentSanitizer;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

    protected static ?string $title = 'Create Update';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source_domain'] = parse_url($data['source_url'], PHP_URL_HOST);
        $data['body'] = app(AuNewsContentSanitizer::class)->sanitize($data['body'] ?? null);

        return $data;
    }
}
