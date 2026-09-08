<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsItemResource\Pages;
use App\Models\NewsItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Str;
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

    protected static ?string $pluralModelLabel = 'All Updates';

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
                        Forms\Components\TextInput::make('title')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state, string $operation): void {
                                if ($operation === 'create' && blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()->maxLength(255)->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->options(self::TYPES)->default('news')->required(),
                        Forms\Components\Textarea::make('excerpt')->rows(3)->columnSpanFull(),
                        Forms\Components\RichEditor::make('body')
                            ->disableToolbarButtons(['attachFiles'])->columnSpanFull(),
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()->schema([
                Forms\Components\Section::make('Publication')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(self::STATUSES)->default('review')->required(),
                        Forms\Components\DateTimePicker::make('published_at'),
                        Forms\Components\TextInput::make('locale')->default('en')->required()->maxLength(10),
                    ]),
                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')->url()->maxLength(255)->label('Image URL'),
                        Forms\Components\Placeholder::make('image_preview')->label('Preview')
                            ->content(fn (Forms\Get $get) => view('filament.news.image-preview', ['url' => $get('image_url')]))
                            ->visible(fn (Forms\Get $get): bool => filled($get('image_url'))),
                    ]),
                Forms\Components\Section::make('Source & Sync')
                    ->schema([
                        Forms\Components\TextInput::make('source_url')
                            ->label('Source URL')->url()->rules(['url:http,https'])->maxLength(255)
                            ->required()->unique(ignoreRecord: true)
                            ->readOnly(fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create')->columnSpanFull(),
                        Forms\Components\TextInput::make('source_domain')->readOnly()->dehydrated(false),
                        Forms\Components\Select::make('sync_mode')
                            ->options(self::SYNC_MODES)->default('local')->required()
                            ->helperText('Choose Keep local edits to protect content from future source imports.'),
                        Forms\Components\TextInput::make('source_changed_at')->readOnly()->dehydrated(false),
                        Forms\Components\TextInput::make('last_scraped_at')->readOnly()->dehydrated(false),
                        Forms\Components\TextInput::make('content_hash')
                            ->readOnly()->dehydrated(false)->hiddenOn('edit')->columnSpanFull(),
                    ])->columns(2),
                ])->columnSpan(['lg' => 1]),
            ])->columns(['lg' => 3]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\Group::make()->schema([
                Infolists\Components\TextEntry::make('type')->hiddenLabel()->badge()
                    ->color('info')->formatStateUsing(fn (string $state): string => Str::title(self::TYPES[$state] ?? $state)),
                Infolists\Components\TextEntry::make('status')->hiddenLabel()->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success', 'review' => 'warning', default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('locale')->hiddenLabel()->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                ])->columns(3),
                Infolists\Components\TextEntry::make('title')->hiddenLabel()->size('lg')->weight('bold'),
                Infolists\Components\TextEntry::make('published_at')->label('Published')->inlineLabel()
                    ->dateTime('j M Y, H:i')->placeholder('Not published'),
                Infolists\Components\Actions::make([
                    Infolists\Components\Actions\Action::make('publish')
                        ->label('Publish')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (NewsItem $record): bool => $record->status !== 'published' && static::canEdit($record))
                        ->action(function (NewsItem $record): void {
                            $record->update(['status' => 'published', 'published_at' => $record->published_at ?? now()]);
                            \Filament\Notifications\Notification::make()->title('News item published')->success()->send();
                        }),
                    Infolists\Components\Actions\Action::make('edit')
                        ->label('Edit')->icon('heroicon-o-pencil-square')->color('gray')
                        ->visible(fn (NewsItem $record): bool => static::canEdit($record))
                        ->url(fn (NewsItem $record): string => static::getUrl('edit', ['record' => $record])),
                    Infolists\Components\Actions\Action::make('archive')
                        ->label('Archive')->icon('heroicon-o-archive-box')->color('danger')
                        ->visible(fn (NewsItem $record): bool => $record->status !== 'archived' && static::canEdit($record))
                        ->action(function (NewsItem $record): void {
                            $record->update(['status' => 'archived']);
                            \Filament\Notifications\Notification::make()->title('News item archived')->success()->send();
                        }),
                    Infolists\Components\Actions\Action::make('openSource')
                        ->label('Open Source')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                        ->visible(fn (NewsItem $record): bool => (bool) preg_match('~^https?://~i', $record->source_url))
                        ->url(fn (NewsItem $record): string => $record->source_url)->openUrlInNewTab(),
                ])->key('news-publication-actions'),
            ]),
            Infolists\Components\Section::make('Hero Image')->schema([
                Infolists\Components\ImageEntry::make('image_url')->hiddenLabel()->height(360)->width('100%'),
            ])->visible(fn (NewsItem $record): bool => filled($record->image_url)),
            Infolists\Components\Section::make('Excerpt')->schema([
                Infolists\Components\TextEntry::make('excerpt')->hiddenLabel()->placeholder('No summary available.'),
            ]),
            Infolists\Components\Section::make('Content')->schema([
                Infolists\Components\TextEntry::make('body')->hiddenLabel()->html()->placeholder('No article content available.'),
            ]),
            Infolists\Components\Section::make('Source & Sync')->schema([
                Infolists\Components\TextEntry::make('source_url')->label('Source URL')
                    ->url(fn (NewsItem $record): ?string => preg_match('~^https?://~i', $record->source_url) ? $record->source_url : null)
                    ->openUrlInNewTab()->columnSpanFull(),
                Infolists\Components\TextEntry::make('source_domain'),
                Infolists\Components\TextEntry::make('sync_mode')->badge(),
                Infolists\Components\TextEntry::make('last_scraped_at')->dateTime('j M Y, H:i')->placeholder('-'),
                Infolists\Components\TextEntry::make('source_changed_at')->dateTime('j M Y, H:i')->placeholder('-'),
                Infolists\Components\TextEntry::make('content_hash')->copyable()->columnSpanFull()->placeholder('-'),
            ])->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(['title', 'excerpt', 'source_url'])->limit(50),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'news' => 'success', 'speech' => 'danger', 'media_advisory' => 'warning',
                        'statement', 'readout' => 'primary', default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => self::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'review' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('locale'),
                Tables\Columns\TextColumn::make('published_at')->dateTime('j M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('sync_mode')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'local' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('last_scraped_at')
                    ->dateTime('j M Y, H:i')->toggleable(),
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
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\ViewAction::make()->button()->color('gray'),
                Tables\Actions\EditAction::make()->button()->color('gray'),
                Tables\Actions\ActionGroup::make([
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
                ]),
            ])
            ->paginationPageOptions([10, 25, 50])->defaultPaginationPageOption(10)
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
            'view' => Pages\ViewNewsItem::route('/{record}'),
            'edit' => Pages\EditNewsItem::route('/{record}/edit'),
        ];
    }
}
