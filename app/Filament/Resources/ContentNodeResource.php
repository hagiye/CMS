<?php

namespace App\Filament\Resources;

use App\Enums\ContentNodeStatus;
use App\Enums\ContentNodeType;
use App\Filament\Resources\ContentNodeResource\Pages;
use App\Models\ContentNode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContentNodeResource extends Resource
{
    protected static ?string $model = ContentNode::class;

    protected static ?string $navigationGroup = 'Handbook';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Content';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Content structure')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->options(function (Get $get, ?ContentNode $record): array {
                                $parentType = ContentNodeType::tryFrom((string) $get('type'))?->parentType();

                                if ($parentType === null) {
                                    return [];
                                }

                                return ContentNode::query()
                                    ->where('type', $parentType->value)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->orderBy('edition', 'desc')
                                    ->orderBy('position')
                                    ->pluck('slug', 'id')
                                    ->all();
                            })
                            ->required(fn (Get $get): bool => ContentNodeType::tryFrom((string) $get('type'))?->parentType() !== null)
                            ->disabled(fn (Get $get): bool => ContentNodeType::tryFrom((string) $get('type'))?->parentType() === null)
                            ->searchable()
                            ->preload()
                            ->label('Parent node'),
                        Forms\Components\Select::make('type')
                            ->options(ContentNodeType::options())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('parent_id', null))
                            ->native(false),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('position')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Editorial lifecycle')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(ContentNodeStatus::options())
                            ->required()
                            ->default(ContentNodeStatus::Draft->value)
                            ->native(false),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->seconds(false)
                            ->helperText('Required for public visibility. It is set automatically when publishing.'),
                        Forms\Components\TextInput::make('edition')
                            ->required(fn (Get $get): bool => $get('type') === ContentNodeType::Edition->value)
                            ->disabled(fn (Get $get): bool => $get('type') !== ContentNodeType::Edition->value)
                            ->dehydrated(fn (Get $get): bool => $get('type') === ContentNodeType::Edition->value)
                            ->maxLength(20)
                            ->placeholder('2023')
                            ->helperText('Child nodes inherit this value from their handbook edition.'),
                        Forms\Components\TextInput::make('revision')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Incremented automatically whenever this node is edited.'),
                        Forms\Components\TextInput::make('source_page_start')
                            ->label('Source page start')
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('source_page_end')
                            ->label('Source page end')
                            ->numeric()
                            ->minValue(1)
                            ->gte('source_page_start'),
                        Forms\Components\Select::make('editor_id')
                            ->relationship('editor', 'name')
                            ->label('Last editor')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\KeyValue::make('meta')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->reorderable()
                    ->helperText('Optional non-structural metadata only. Hierarchy, edition, status, and page ranges use dedicated fields.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->label('Order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.slug')
                    ->label('Parent')
                    ->searchable()
                    ->placeholder('Handbook root')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContentNodeType::tryFrom($state)?->label() ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ContentNodeStatus $state): string => $state->label())
                    ->color(fn (ContentNodeStatus $state): string => match ($state) {
                        ContentNodeStatus::Draft => 'gray',
                        ContentNodeStatus::Review => 'warning',
                        ContentNodeStatus::Published => 'success',
                        ContentNodeStatus::Archived => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('edition')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('revision')
                    ->sortable(),
                Tables\Columns\TextColumn::make('editor.name')
                    ->label('Last editor')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->relationship('parent', 'slug')
                    ->searchable()
                    ->preload()
                    ->label('Parent'),
                Tables\Filters\SelectFilter::make('type')
                    ->options(ContentNodeType::options()),
                Tables\Filters\SelectFilter::make('edition')
                    ->options(fn (): array => ContentNode::query()
                        ->whereNotNull('edition')
                        ->distinct()
                        ->orderByDesc('edition')
                        ->pluck('edition', 'edition')
                        ->all()),
                Tables\Filters\SelectFilter::make('status')
                    ->options(ContentNodeStatus::options()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable('position');
    }

    public static function getRelations(): array
    {
        return [
            ContentNodeResource\RelationManagers\TranslationsRelationManager::class,
            ContentNodeResource\RelationManagers\DocumentsRelationManager::class,
            ContentNodeResource\RelationManagers\LinksRelationManager::class,
            ContentNodeResource\RelationManagers\ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentNodes::route('/'),
            'create' => Pages\CreateContentNode::route('/create'),
            'edit' => Pages\EditContentNode::route('/{record}/edit'),
        ];
    }
}
