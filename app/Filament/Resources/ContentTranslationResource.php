<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentTranslationResource\Pages;
use App\Filament\Resources\ContentTranslationResource\RelationManagers;
use App\Models\ContentTranslation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentTranslationResource extends Resource
{
    protected static ?string $model = ContentTranslation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentTranslations::route('/'),
            'create' => Pages\CreateContentTranslation::route('/create'),
            'edit' => Pages\EditContentTranslation::route('/{record}/edit'),
        ];
    }
}
