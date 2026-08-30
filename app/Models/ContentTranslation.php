<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

class ContentTranslation extends Model
{
    use HasFactory, Searchable;

    protected $fillable = ['content_node_id', 'locale', 'title', 'body'];

    #[SearchUsingFullText(['title', 'body'])]
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->node?->isPublished() ?? false;
    }

    public function node()
    {
        return $this->belongsTo(ContentNode::class, 'content_node_id');
    }
}
