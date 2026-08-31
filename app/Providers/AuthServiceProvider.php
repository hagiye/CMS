<?php

namespace App\Providers;

use App\Models\Bookmark;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Models\Document;
use App\Models\Link;
use App\Policies\BookmarkPolicy;
use App\Policies\ContentNodePolicy;
use App\Policies\ContentTranslationPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\LinkPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Bookmark::class => BookmarkPolicy::class,
        ContentNode::class => ContentNodePolicy::class,
        ContentTranslation::class => ContentTranslationPolicy::class,
        Document::class => DocumentPolicy::class,
        Link::class => LinkPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
