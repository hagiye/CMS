<?php

namespace App\Filament\Resources\ContentNodeResource\RelationManagers;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function form(Form $form): Form
    {
        return $form
            ->schema(DocumentResource::documentFields())
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Original filename')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('external_url')
                    ->label('External URL')
                    ->url(fn (Document $record): ?string => $record->external_url)
                    ->openUrlInNewTab()
                    ->limit(45),
                Tables\Columns\TextColumn::make('page_start')
                    ->label('Pages')
                    ->formatStateUsing(fn ($state, Document $record): string => match (true) {
                        $record->page_start !== null && $record->page_end !== null => "{$record->page_start}–{$record->page_end}",
                        $record->page_start !== null => (string) $record->page_start,
                        default => '—',
                    }),
                Tables\Columns\TextColumn::make('imported_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
