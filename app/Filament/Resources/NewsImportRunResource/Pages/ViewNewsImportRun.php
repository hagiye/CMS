<?php

namespace App\Filament\Resources\NewsImportRunResource\Pages;

use App\Filament\Resources\NewsImportRunResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewNewsImportRun extends ViewRecord
{
    protected static string $resource = NewsImportRunResource::class;

    protected static ?string $title = 'Import Run Details';

    protected function getHeaderActions(): array
    {
        return [Action::make('back')->label('Back')->icon('heroicon-o-arrow-left')
            ->color('gray')->url(NewsImportRunResource::getUrl('index'))];
    }
}
