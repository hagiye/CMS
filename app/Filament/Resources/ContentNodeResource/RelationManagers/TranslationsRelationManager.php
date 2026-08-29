<?php

namespace App\Filament\Resources\ContentNodeResource\RelationManagers;

use App\Filament\Resources\ContentTranslationResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TranslationsRelationManager extends RelationManager
{
    protected static string $relationship = 'translations';

    protected static ?string $title = 'Translations';

    public function form(Form $form): Form
    {
        return $form
            ->schema(ContentTranslationResource::translationFields($this->getOwnerRecord()->getKey()))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('locale')
                    ->formatStateUsing(fn (string $state): string => ContentTranslationResource::languageOptions()[$state] ?? strtoupper($state))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->formatStateUsing(fn (?string $state): string => strip_tags($state ?? ''))
                    ->limit(80),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
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
