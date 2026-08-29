<?php

namespace App\Models;

use App\Enums\ContentNodeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentNode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'type',
        'slug',
        'position',
        'status',
        'published_at',
        'edition',
        'source_page_start',
        'source_page_end',
        'revision',
        'editor_id',
        'meta',
    ];

    protected $casts = [
        'status' => ContentNodeStatus::class,
        'published_at' => 'datetime',
        'source_page_start' => 'integer',
        'source_page_end' => 'integer',
        'revision' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContentNode $node) {
            if ($node->status === ContentNodeStatus::Published && $node->published_at === null) {
                $node->published_at = now();
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function translations()
    {
        return $this->hasMany(ContentTranslation::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function bookmarkedByUsers()
    {
        return $this->belongsToMany(User::class, 'bookmarks')->withTimestamps();
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', ContentNodeStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === ContentNodeStatus::Published
            && $this->published_at !== null
            && $this->published_at->isPast();
    }
}
