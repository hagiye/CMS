<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_url',
        'status',
        'started_at',
        'finished_at',
        'items_found',
        'items_created',
        'items_updated',
        'items_skipped',
        'items_failed',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];
}
