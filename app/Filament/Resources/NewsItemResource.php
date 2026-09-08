<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsItemResource\Pages;
use App\Models\NewsItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsItemResource extends Resource
{
    protected static ?string $model = NewsItem::class;

    protected static ?string $navigationGroup = 'News & Media';

    protected static ?string $navigationLabel = 'All Updates';

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    private const TYPES = [
        'news' => 'News',
        'press_release' => 'Press release',
        'speech' => 'Speech',
        'readout' => 'Readout',
        'event' => 'Event',
        'media_advisory' => 'Media advisory',
        'statement' => 'Statement',
    ];

    private const STATUSES = [
        'review' => 'Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    private const SYNC_MODES = [
        'source' => 'Follow AU source',
        'local' => 'Keep local edits',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'excerpt', 'source_url'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')->required()->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()->maxLength(255)->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->options(self::TYPES)->default('news')->required(),
                        Forms\Components\Textarea::make('excerpt')->rows(3)->columnSpanFull(),
                        Forms\Components\RichEditor::make('body')
                            ->disableToolbarButtons(['attachFiles'])->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Publication')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(self::STATUSES)->default('review')->required(),
                        Forms\Components\DateTimePicker::make('published_at'),
                        Forms\Components\TextInput::make('locale')->default('en')->required()->maxLength(10),
                    ])->columns(3),
                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')->url()->maxLength(255)->label('Image URL'),
                    ]),
                Forms\Components\Section::make('Source & Sync')
                    ->schema([
                        Forms\Components\TextInput::make('source_url')
                            ->label('Source URL')->url()->readOnly()->dehydrated(false)->columnSpanFull(),
                        Forms\Components\TextInput::make('source_domain')->readOnly()->dehydrated(false),
                        Forms\Components\Select::make('sync_mode')
                            ->options(self::SYNC_MODES)->required()
                            ->helperText('Choose Keep local edits to protect content from future source imports.'),
                        Forms\Components\TextInput::make('source_changed_at')->readOnly()->dehydrated(false),
                        Forms\Components\TextInput::make('last_scraped_at')->readOnly()->dehydrated(false),
                        Forms\Components\TextInput::make('content_hash')
                            ->readOnly()->dehydrated(false)->hiddenOn('edit')->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(['title', 'excerpt', 'source_url'])->limit(70)->wrap(),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'review' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('locale'),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('sync_mode')->badge(),
                Tables\Columns\TextColumn::make('last_scraped_at')
                    ->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(self::TYPES),
                Tables\Filters\SelectFilter::make('status')->options(self::STATUSES),
                Tables\Filters\SelectFilter::make('locale')->options(
                    fn (): array => NewsItem::query()->distinct()->orderBy('locale')->pluck('locale', 'locale')->all()
                ),
                Tables\Filters\SelectFilter::make('sync_mode')->options(self::SYNC_MODES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label('Publish')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (NewsItem $record): bool => $record->status !== 'published' && static::canEdit($record))
                    ->action(function (NewsItem $record): void {
                        $record->update([
                            'status' => 'published',
                            'published_at' => $record->published_at ?? now(),
                        ]);
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')->icon('heroicon-o-archive-box')->color('gray')
                    ->visible(fn (NewsItem $record): bool => $record->status !== 'archived' && static::canEdit($record))
                    ->action(function (NewsItem $record): void {
                        $record->update(['status' => 'archived']);
                    }),
            ])
            ->defaultSort('published_at', 'desc');
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
            'index' => Pages\ListNewsItems::route('/'),
            'create' => Pages\CreateNewsItem::route('/create'),
            'edit' => Pages\EditNewsItem::route('/{record}/edit'),
        ];
    }
}
