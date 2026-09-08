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

    protected static ?string $modelLabel = 'Import Run';

    protected static ?string $pluralModelLabel = 'Import Runs';

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
            Section::make('Overview')->schema([
                TextEntry::make('source_url')->label('Source URL')->columnSpanFull()
                    ->url(fn (NewsImportRun $record): ?string => preg_match('~^https?://~i', $record->source_url) ? $record->source_url : null)
                    ->openUrlInNewTab(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'failed' => 'danger', 'running' => 'warning', 'dispatched', 'completed' => 'success', default => 'gray',
                    }),
                TextEntry::make('started_at')->dateTime('j M Y, H:i'),
                TextEntry::make('finished_at')->dateTime()->placeholder('—'),
                TextEntry::make('items_found')->numeric(),
                TextEntry::make('items_created')->numeric(),
                TextEntry::make('items_updated')->numeric(),
                TextEntry::make('items_skipped')->numeric(),
                TextEntry::make('items_failed')->numeric(),
                TextEntry::make('error_message')->placeholder('No errors')->columnSpanFull(),
            ])->columns(1)->inlineLabel(),
            Section::make('Metadata')->schema([
                TextEntry::make('metadata')
                    ->hiddenLabel()->copyable()
                    ->state(fn (NewsImportRun $record): string => (string) json_encode(
                        $record->metadata,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ))
                    ->visible(fn (NewsImportRun $record): bool => filled($record->metadata))
                    ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono'])
                    ->columnSpanFull(),
            ])->visible(fn (NewsImportRun $record): bool => filled($record->metadata)),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->dateTime('j M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'warning',
                        'dispatched', 'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_found')->label('Found')->numeric(),
                Tables\Columns\TextColumn::make('items_created')->label('Created')->numeric(),
                Tables\Columns\TextColumn::make('items_updated')->label('Updated')->numeric(),
                Tables\Columns\TextColumn::make('items_skipped')->label('Skipped')->numeric(),
                Tables\Columns\TextColumn::make('items_failed')->label('Failed')->numeric(),
                Tables\Columns\TextColumn::make('finished_at')->dateTime('j M Y, H:i'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->button()->color('gray')->icon(null),
            ])
            ->paginationPageOptions([10, 25, 50])->defaultPaginationPageOption(10)
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
