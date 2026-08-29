<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['content_node_id','kind','title','path','external_url','meta'];
    protected $casts = ['meta' => 'array'];

    // Specify the foreign key explicitly
    public function node()
    {
        return $this->belongsTo(ContentNode::class, 'content_node_id');
    }
}
