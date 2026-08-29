<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Link extends Model
{
    use HasFactory;

    protected $fillable = ['content_node_id','label','url','meta'];
    protected $casts = ['meta' => 'array'];

    // 👇 specify the foreign key explicitly
    public function node()
    {
        return $this->belongsTo(ContentNode::class, 'content_node_id');
    }
}
