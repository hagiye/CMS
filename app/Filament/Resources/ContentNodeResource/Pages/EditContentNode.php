<?php

namespace App\Filament\Resources\ContentNodeResource\Pages;

use App\Filament\Resources\ContentNodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentNode extends EditRecord
{
    protected static string $resource = ContentNodeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['editor_id'] = auth()->id();
        $data['revision'] = $this->record->revision + 1;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
