<?php

namespace App\Filament\Resources\ContentNodeResource\Pages;

use App\Filament\Resources\ContentNodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentNodes extends ListRecords
{
    protected static string $resource = ContentNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
