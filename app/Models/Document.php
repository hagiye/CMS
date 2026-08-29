<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_node_id',
        'kind',
        'title',
        'path',
        'external_url',
        'page_start',
        'page_end',
        'meta',
    ];

    protected $casts = [
        'page_start' => 'integer',
        'page_end' => 'integer',
        'meta' => 'array',
    ];

    // Specify the foreign key explicitly
    public function node()
    {
        return $this->belongsTo(ContentNode::class, 'content_node_id');
    }
}
