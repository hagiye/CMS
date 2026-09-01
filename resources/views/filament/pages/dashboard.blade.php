<x-filament-panels::page class="au-dashboard-page">
    <div class="au-dashboard">
        <section class="au-welcome-card">
            <div class="au-welcome-accent"></div>
            <div>
                <span>Welcome back</span>
                <h2>Manage handbook editions, content, translations, and documents.</h2>
                <p>Centralized management for all AU Handbook resources.</p>
            </div>
            <div class="au-welcome-watermark" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-globe-alt" />
            </div>
        </section>

        <section class="au-stats-grid" aria-label="Handbook statistics">
            @foreach ($stats as $stat)
                <a href="{{ $stat['url'] }}" class="au-stat-card">
                    <span class="au-card-icon"><x-filament::icon :icon="$stat['icon']" /></span>
                    <span class="au-stat-copy">
                        <span>{{ $stat['label'] }}</span>
                        <strong>{{ $stat['value'] }}</strong>
                        <small>+{{ number_format($stat['delta']) }} this month <span>↗</span></small>
                    </span>
                </a>
            @endforeach
        </section>

        <section class="au-feature-grid">
            <article class="au-panel au-edition-card">
                <header class="au-panel-header">
                    <div><x-filament::icon icon="heroicon-o-book-open" /><h3>Current Edition</h3></div>
                </header>

                @if ($currentEdition)
                    <div class="au-edition-content">
                        <div class="au-book-cover" aria-hidden="true">
                            <div class="au-book-seal"><x-filament::icon icon="heroicon-o-globe-alt" /></div>
                            <strong>AFRICAN UNION<br>HANDBOOK</strong>
                            <span>{{ $currentEdition->edition }} EDITION</span>
                        </div>
                        <div class="au-edition-details">
                            <h4>{{ $currentEditionTitle }}</h4>
                            <span class="au-badge au-badge-{{ $currentEdition->status->value }}">{{ $currentEdition->status->label() }}</span>
                            <dl>
                                <div><dt>Published on</dt><dd>{{ $currentEdition->published_at?->format('M j, Y') ?? 'Not published' }}</dd></div>
                                <div><dt>Revision</dt><dd>{{ $currentEdition->revision }}</dd></div>
                                <div><dt>Languages</dt><dd>{{ $currentEdition->translations->count() }}</dd></div>
                                <div><dt>Document</dt><dd>{{ $editionDocument?->title ?? 'Not attached' }}</dd></div>
                            </dl>
                            <a class="au-secondary-button" href="{{ $currentEditionUrl }}">View Edition <span>↗</span></a>
                        </div>
                    </div>
                @else
                    <div class="au-empty-state">
                        <x-filament::icon icon="heroicon-o-book-open" />
                        <p>No handbook edition has been created yet.</p>
                        <a href="{{ $currentEditionUrl }}">Create edition</a>
                    </div>
                @endif
            </article>

            <article class="au-panel au-import-card">
                <header class="au-panel-header">
                    <div><x-filament::icon icon="heroicon-o-cloud-arrow-up" /><h3>Latest Import</h3></div>
                    <a href="{{ $urls['documents'] }}">View all imports</a>
                </header>

                @if ($latestImport)
                    <div class="au-import-body">
                        <h4>{{ $latestImport->original_filename ?? $latestImport->title ?? 'Imported document' }}</h4>
                        <div class="au-progress-row"><span class="au-progress-track"><i></i></span><strong>100%</strong></div>
                        <div class="au-import-status"><span>Import completed</span><time>{{ ($latestImport->imported_at ?? $latestImport->created_at)->diffForHumans() }}</time></div>
                        <dl>
                            <div><dt>Imported</dt><dd>{{ ($latestImport->imported_at ?? $latestImport->created_at)->format('M j, Y g:i A') }}</dd></div>
                            <div><dt>Pages</dt><dd>{{ $latestImport->page_start && $latestImport->page_end ? $latestImport->page_start.'–'.$latestImport->page_end : 'Not specified' }}</dd></div>
                            <div><dt>Status</dt><dd><span class="au-badge au-badge-published">Complete</span></dd></div>
                            <div><dt>Size</dt><dd>{{ $latestImportSize ?? 'External source' }}</dd></div>
                        </dl>
                        <a class="au-panel-link" href="{{ $latestImportUrl }}">Review import details</a>
                    </div>
                @else
                    <div class="au-empty-state">
                        <x-filament::icon icon="heroicon-o-cloud-arrow-up" />
                        <p>No imported PDF is available yet.</p>
                        <a href="{{ $urls['createDocument'] }}">Add document</a>
                    </div>
                @endif
            </article>
        </section>

        <section class="au-lower-grid">
            <article class="au-panel au-recent-card">
                <header class="au-panel-header">
                    <div><x-filament::icon icon="heroicon-o-list-bullet" /><h3>Recent Content</h3></div>
                    <a href="{{ $urls['content'] }}">View all content</a>
                </header>
                <div class="au-table-wrap">
                    <table class="au-content-table">
                        <thead><tr><th>Title</th><th>Section</th><th>Status</th><th>Updated</th><th>Updated by</th></tr></thead>
                        <tbody>
                            @forelse ($recentContent as $node)
                                @php
                                    $nodeTitle = $node->translations->firstWhere('locale', 'en')?->title ?? $node->translations->first()?->title ?? $node->slug;
                                    $parentTitle = $node->parent?->translations?->firstWhere('locale', 'en')?->title ?? $node->parent?->slug ?? 'Handbook root';
                                @endphp
                                <tr>
                                    <td><a href="{{ \App\Filament\Resources\ContentNodeResource::getUrl('edit', ['record' => $node]) }}">{{ $nodeTitle }}</a></td>
                                    <td>{{ $parentTitle }}</td>
                                    <td><span class="au-badge au-badge-{{ $node->status->value }}">{{ $node->status->label() }}</span></td>
                                    <td>{{ $node->updated_at->diffForHumans(short: true) }}</td>
                                    <td>{{ $node->editor?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="au-table-empty">No content has been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="au-panel au-actions-card">
                <header class="au-panel-header"><div><x-filament::icon icon="heroicon-o-bolt" /><h3>Quick Actions</h3></div></header>
                <div class="au-action-list">
                    @if (auth()->user()?->canCreateEditorialContent())
                        <a class="au-action-primary" href="{{ $urls['createDocument'] }}"><x-filament::icon icon="heroicon-o-cloud-arrow-up" /> Upload Handbook PDF</a>
                        <a href="{{ $urls['createContent'] }}"><x-filament::icon icon="heroicon-o-document-plus" /> Add Content</a>
                        <a href="{{ $urls['createTranslation'] }}"><x-filament::icon icon="heroicon-o-language" /> Add Translation</a>
                    @endif
                    <a href="{{ $urls['documents'] }}"><x-filament::icon icon="heroicon-o-folder" /> Manage Documents</a>
                </div>
            </article>

            <article class="au-panel au-activity-card">
                <header class="au-panel-header"><div><x-filament::icon icon="heroicon-o-bolt" /><h3>Recent Activity</h3></div></header>
                <div class="au-activity-list">
                    @forelse ($activities as $activity)
                        <div class="au-activity-item">
                            <span class="au-activity-icon"><x-filament::icon :icon="$activity['icon']" /></span>
                            <div><strong>{{ $activity['label'] }}</strong><small>{{ $activity['at']->format('M j, Y g:i A') }} · {{ $activity['detail'] }}</small></div>
                            <i></i>
                        </div>
                    @empty
                        <div class="au-empty-inline">Activity will appear here as content is managed.</div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-filament-panels::page>
