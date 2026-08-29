<?php

namespace App\Models;

use App\Enums\ContentNodeStatus;
use App\Enums\ContentNodeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

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
        'source_document_id',
        'import_key',
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
            $node->validateHierarchy();

            if ($node->status === ContentNodeStatus::Published && $node->published_at === null) {
                $node->published_at = now();
            }
        });

        static::saved(function (ContentNode $node) {
            if (! $node->wasChanged('edition')) {
                return;
            }

            $node->children()->get()->each(function (ContentNode $child) use ($node) {
                if ($child->edition !== $node->edition) {
                    $child->update(['edition' => $node->edition]);
                }
            });
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

    public function sourceDocument()
    {
        return $this->belongsTo(Document::class, 'source_document_id');
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

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->where('type', ContentNodeType::Edition->value);
    }

    public function scopeForEdition($query, string $edition)
    {
        return $query->where('edition', $edition);
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

    public function nodeType(): ?ContentNodeType
    {
        return ContentNodeType::tryFrom($this->type);
    }

    private function validateHierarchy(): void
    {
        $type = $this->nodeType();

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => 'The selected content type is invalid.',
            ]);
        }

        $expectedParentType = $type->parentType();

        if ($expectedParentType === null) {
            if ($this->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A handbook edition cannot have a parent.',
                ]);
            }

            if (blank($this->edition)) {
                throw ValidationException::withMessages([
                    'edition' => 'The edition is required for a handbook edition node.',
                ]);
            }
        } else {
            if ($this->parent_id === null) {
                throw ValidationException::withMessages([
                    'parent_id' => "A {$type->label()} must belong to a {$expectedParentType->label()}.",
                ]);
            }

            $parent = static::query()->find($this->parent_id);

            if (! $parent || $parent->nodeType() !== $expectedParentType) {
                throw ValidationException::withMessages([
                    'parent_id' => "A {$type->label()} must belong to a {$expectedParentType->label()}.",
                ]);
            }

            if (blank($parent->edition)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent is not assigned to a handbook edition.',
                ]);
            }

            $this->edition = $parent->edition;
        }

        if (! $this->exists) {
            return;
        }

        $allowedChildTypes = array_keys($type->childOptions());
        $hasInvalidChildren = $allowedChildTypes === []
            ? $this->children()->exists()
            : $this->children()->whereNotIn('type', $allowedChildTypes)->exists();

        if ($hasInvalidChildren) {
            throw ValidationException::withMessages([
                'type' => 'This type is incompatible with one or more existing child nodes.',
            ]);
        }
    }
}
