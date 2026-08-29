<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationGroup = 'Handbook';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Documents';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('content_node_id')
                ->relationship('node', 'slug')
                ->searchable()
                ->label('Node'),

            Forms\Components\Select::make('kind')
                ->options([
                    'pdf'   => 'pdf',
                    'image' => 'image',
                    'link'  => 'link',
                ])
                ->required()
                ->native(false),

            // One of these two should be provided (we’ll soft-enforce via helper text)
            Forms\Components\TextInput::make('path')
                ->label('Storage Path (if uploaded)')
                ->placeholder('storage/app/public/...'),

            Forms\Components\TextInput::make('external_url')
                ->label('External URL (e.g., AU PDF)')
                ->placeholder('https://...'),

            Forms\Components\TextInput::make('title')
                ->label('Title'),

            Forms\Components\KeyValue::make('meta')
                ->keyLabel('key')
                ->valueLabel('value')
                ->reorderable()
                ->helperText('Optional: page_start, page_end, etc.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('kind')->searchable()->badge(),
            Tables\Columns\TextColumn::make('title')->limit(40)->wrap(),
            Tables\Columns\TextColumn::make('external_url')->limit(50)->wrap(),
            Tables\Columns\TextColumn::make('node.slug')->label('Node')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit'   => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
