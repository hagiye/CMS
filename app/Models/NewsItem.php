<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'source_url',
        'source_domain',
        'image_url',
        'published_at',
        'locale',
        'status',
        'sync_mode',
        'content_hash',
        'last_scraped_at',
        'source_changed_at',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'last_scraped_at' => 'datetime',
        'source_changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function scopePublished($query)
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at');
    }
}
