<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsImportRunResource\Pages;
use App\Models\NewsImportRun;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NewsImportRunResource extends Resource
{
    protected static ?string $model = NewsImportRun::class;

    protected static ?string $navigationGroup = 'News & Media';

    protected static ?string $navigationLabel = 'Import Runs';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Import details')->schema([
                TextEntry::make('source_url')->label('Source URL')->columnSpanFull(),
                TextEntry::make('status')->badge(),
                TextEntry::make('started_at')->dateTime(),
                TextEntry::make('finished_at')->dateTime()->placeholder('—'),
            ])->columns(3),
            Section::make('Results')->schema([
                TextEntry::make('items_found')->numeric(),
                TextEntry::make('items_created')->numeric(),
                TextEntry::make('items_updated')->numeric(),
                TextEntry::make('items_skipped')->numeric(),
                TextEntry::make('items_failed')->numeric(),
            ])->columns(5),
            Section::make('Diagnostics')->schema([
                TextEntry::make('error_message')->placeholder('No errors')->columnSpanFull(),
                TextEntry::make('metadata')
                    ->state(fn (NewsImportRun $record): string => (string) json_encode(
                        $record->metadata,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ))
                    ->visible(fn (NewsImportRun $record): bool => filled($record->metadata))
                    ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono'])
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'warning',
                        'dispatched' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_found')->numeric(),
                Tables\Columns\TextColumn::make('items_created')->numeric(),
                Tables\Columns\TextColumn::make('items_updated')->numeric(),
                Tables\Columns\TextColumn::make('items_skipped')->numeric(),
                Tables\Columns\TextColumn::make('items_failed')->numeric(),
                Tables\Columns\TextColumn::make('finished_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsImportRuns::route('/'),
            'view' => Pages\ViewNewsImportRun::route('/{record}'),
        ];
    }
}
