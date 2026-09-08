<?php

namespace App\Filament\Resources\NewsImportRunResource\Pages;

use App\Filament\Resources\NewsImportRunResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewNewsImportRun extends ViewRecord
{
    protected static string $resource = NewsImportRunResource::class;

    protected static string $view = 'filament.news.import-run-view';

    public function getTitle(): string
    {
        return 'Import Run #'.$this->record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('back')->label('Back')->icon('heroicon-o-arrow-left')
            ->color('gray')->url(NewsImportRunResource::getUrl('index'))];
    }
}
