<?php

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsItems extends ListRecords
{
    protected static string $resource = NewsItemResource::class;

    protected static ?string $title = 'All Updates';

    public function getSubheading(): ?string
    {
        return 'Manage news, press releases, speeches, readouts and other AU updates.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Create Update')->icon('heroicon-o-plus'),
        ];
    }
}
