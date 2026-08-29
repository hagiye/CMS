<?php

namespace App\Filament\Resources\ContentNodeResource\Pages;

use App\Filament\Resources\ContentNodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContentNode extends CreateRecord
{
    protected static string $resource = ContentNodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['editor_id'] = auth()->id();
        $data['revision'] = 1;

        return $data;
    }
}
