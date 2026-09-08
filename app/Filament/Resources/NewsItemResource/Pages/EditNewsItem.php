<?php

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use App\Services\AuNews\AuNewsContentSanitizer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsItem extends EditRecord
{
    protected static string $resource = NewsItemResource::class;

    protected static ?string $title = 'Edit News Item';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['body'] = app(AuNewsContentSanitizer::class)->sanitize($data['body'] ?? null);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('publish')->label('Publish')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (): bool => $this->record->status !== 'published')
                ->action(function (): void {
                    $this->record->update(['status' => 'published', 'published_at' => $this->record->published_at ?? now()]);
                    $this->refreshFormData(['status', 'published_at']);
                }),
            Actions\Action::make('archive')->label('Archive')->icon('heroicon-o-archive-box')->color('danger')
                ->visible(fn (): bool => $this->record->status !== 'archived')
                ->action(function (): void {
                    $this->record->update(['status' => 'archived']);
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
