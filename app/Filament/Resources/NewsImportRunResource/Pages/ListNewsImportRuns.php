<?php

namespace App\Filament\Resources\NewsImportRunResource\Pages;

use App\Filament\Resources\NewsImportRunResource;
use App\Jobs\ImportAuNews;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListNewsImportRuns extends ListRecords
{
    protected static string $resource = NewsImportRunResource::class;

    protected static ?string $title = 'Import Runs';

    protected static string $view = 'filament.news.import-runs-list';

    public function getSubheading(): ?string
    {
        return 'History of AU News imports and synchronization runs.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncAuNews')
                ->label('Sync AU News')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(function (): void {
                    ImportAuNews::dispatch();

                    Notification::make()
                        ->title('AU News sync queued')
                        ->success()
                        ->send();
                }),
        ];
    }
}
