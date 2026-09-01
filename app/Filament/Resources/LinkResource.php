<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LinkResource\Pages;
use App\Models\Link;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LinkResource extends Resource
{
    protected static ?string $model = Link::class;

    protected static ?string $navigationGroup = 'Handbook';

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Links';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'label';

    /**
     * @return array<Forms\Components\Component>
     */
    public static function linkFields(): array
    {
        return [
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('url')
                ->required()
                ->url()
                ->rules(['url:http,https'])
                ->maxLength(255)
                ->placeholder('https://au.int/...'),
            Forms\Components\KeyValue::make('meta')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->reorderable()
                ->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('content_node_id')
                    ->relationship('node', 'slug')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Content node'),
                ...static::linkFields(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('node.slug')
                    ->label('Content node')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->url(fn (Link $record): string => $record->url)
                    ->openUrlInNewTab()
                    ->limit(70)
                    ->searchable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLinks::route('/'),
            'create' => Pages\CreateLink::route('/create'),
            'edit' => Pages\EditLink::route('/{record}/edit'),
        ];
    }
}
