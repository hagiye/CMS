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

    /**
     * @return array<Forms\Components\Component>
     */
    public static function documentFields(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('kind')
                ->options([
                    'pdf' => 'PDF',
                    'image' => 'Image',
                    'link' => 'Other link',
                ])
                ->required()
                ->default('pdf')
                ->native(false),
            Forms\Components\FileUpload::make('path')
                ->label('Upload')
                ->disk('public')
                ->directory('handbook-documents')
                ->visibility('public')
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ])
                ->maxSize(20 * 1024)
                ->downloadable()
                ->openable()
                ->requiredWithout('external_url')
                ->helperText('Upload a PDF or image, or provide an external URL.'),
            Forms\Components\TextInput::make('external_url')
                ->label('External URL')
                ->url()
                ->maxLength(255)
                ->requiredWithout('path')
                ->placeholder('https://au.int/...'),
            Forms\Components\TextInput::make('page_start')
                ->label('Page start')
                ->numeric()
                ->minValue(1),
            Forms\Components\TextInput::make('page_end')
                ->label('Page end')
                ->numeric()
                ->minValue(1)
                ->gte('page_start'),
            Forms\Components\TextInput::make('original_filename')
                ->label('Original filename')
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('checksum')
                ->label('SHA-256 checksum')
                ->disabled()
                ->dehydrated(false),
            Forms\Components\DateTimePicker::make('imported_at')
                ->label('Imported at')
                ->disabled()
                ->dehydrated(false)
                ->seconds(false),
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
                ...static::documentFields(),
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
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('path')
                    ->label('Uploaded file')
                    ->limit(35)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Original filename')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('checksum')
                    ->label('Checksum')
                    ->limit(12)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('external_url')
                    ->label('External URL')
                    ->url(fn (Document $record): ?string => $record->external_url)
                    ->openUrlInNewTab()
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('page_start')
                    ->label('Pages')
                    ->formatStateUsing(fn ($state, Document $record): string => match (true) {
                        $record->page_start !== null && $record->page_end !== null => "{$record->page_start}–{$record->page_end}",
                        $record->page_start !== null => (string) $record->page_start,
                        default => '—',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
