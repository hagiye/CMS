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

    // Call only while holding a row lock inside a database transaction.
    public function completeIfProcessed(): void
    {
        $processed = $this->items_created + $this->items_updated
            + $this->items_skipped + $this->items_failed;

        if ($this->status === 'processing' && $processed >= $this->items_found) {
            $this->status = $this->items_failed > 0 ? 'completed_with_errors' : 'completed';
            $this->finished_at = now();
        }
    }
}
