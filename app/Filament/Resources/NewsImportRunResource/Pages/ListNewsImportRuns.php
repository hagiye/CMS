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
