<?php

namespace App\Filament\Resources\ContentTranslationResource\Pages;

use App\Filament\Resources\ContentTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentTranslation extends EditRecord
{
    protected static string $resource = ContentTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
