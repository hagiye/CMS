<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentTranslationResource\Pages;
use App\Models\ContentTranslation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ContentTranslationResource extends Resource
{
    protected static ?string $model = ContentTranslation::class;

    protected static ?string $navigationGroup = 'Handbook';

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationLabel = 'Translations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * @return array<string, string>
     */
    public static function languageOptions(): array
    {
        return [
            'ar' => 'Arabic',
            'en' => 'English',
            'fr' => 'French',
            'pt' => 'Portuguese',
            'es' => 'Spanish',
            'sw' => 'Swahili',
        ];
    }

    /**
     * @return array<Forms\Components\Component>
     */
    public static function translationFields(?int $contentNodeId = null): array
    {
        return [
            Forms\Components\Select::make('locale')
                ->options(static::languageOptions())
                ->required()
                ->searchable()
                ->native(false)
                ->rule(function (Get $get, ?ContentTranslation $record) use ($contentNodeId) {
                    $nodeId = $contentNodeId ?? $get('content_node_id');

                    return Rule::unique('content_translations', 'locale')
                        ->where('content_node_id', $nodeId)
                        ->ignore($record?->getKey());
                }),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\RichEditor::make('body')
                ->toolbarButtons([
                    'blockquote',
                    'bold',
                    'bulletList',
                    'h2',
                    'h3',
                    'italic',
                    'link',
                    'orderedList',
                    'redo',
                    'strike',
                    'undo',
                ])
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
                ...static::translationFields(),
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
                Tables\Columns\TextColumn::make('locale')
                    ->formatStateUsing(fn (string $state): string => static::languageOptions()[$state] ?? strtoupper($state))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('body')
                    ->formatStateUsing(fn (?string $state): string => strip_tags($state ?? ''))
                    ->limit(80)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('locale')
                    ->options(static::languageOptions()),
            ])
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
            'index' => Pages\ListContentTranslations::route('/'),
            'create' => Pages\CreateContentTranslation::route('/create'),
            'edit' => Pages\EditContentTranslation::route('/{record}/edit'),
        ];
    }
}
