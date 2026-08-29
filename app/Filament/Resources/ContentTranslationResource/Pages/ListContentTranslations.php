<?php

namespace App\Filament\Resources\ContentTranslationResource\Pages;

use App\Filament\Resources\ContentTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentTranslations extends ListRecords
{
    protected static string $resource = ContentTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
