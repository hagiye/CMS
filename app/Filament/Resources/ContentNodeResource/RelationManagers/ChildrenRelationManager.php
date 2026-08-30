<?php

namespace App\Filament\Resources\ContentNodeResource\RelationManagers;

use App\Enums\ContentNodeStatus;
use App\Enums\ContentNodeType;
use App\Filament\Resources\ContentNodeResource;
use App\Models\ContentNode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Child content';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options($this->allowedChildTypes())
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('position')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(fn (): array => ContentNodeResource::allowedStatusOptions())
                    ->default(ContentNodeStatus::Draft->value)
                    ->required()
                    ->native(false),
                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Publish at')
                    ->seconds(false),
                Forms\Components\TextInput::make('edition')
                    ->maxLength(20)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Inherited from the parent handbook edition.'),
                Forms\Components\TextInput::make('source_page_start')
                    ->label('Source page start')
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('source_page_end')
                    ->label('Source page end')
                    ->numeric()
                    ->minValue(1)
                    ->gte('source_page_start'),
                Forms\Components\Select::make('source_document_id')
                    ->relationship('sourceDocument', 'title')
                    ->label('Source PDF')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('import_key')
                    ->label('Import key')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\KeyValue::make('meta')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->reorderable()
                    ->helperText('Optional non-structural metadata only.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('slug')
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
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
                    }),
                Tables\Columns\TextColumn::make('edition'),
                Tables\Columns\TextColumn::make('sourceDocument.title')
                    ->label('Source PDF')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => $this->allowedChildTypes() !== [])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['editor_id'] = auth()->id();
                        $data['revision'] = 1;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, ContentNode $record): array {
                        $data['editor_id'] = auth()->id();
                        $data['revision'] = $record->revision + 1;

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function allowedChildTypes(): array
    {
        return $this->getOwnerRecord()->nodeType()?->childOptions() ?? [];
    }
}
