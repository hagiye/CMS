<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['content_node_id','locale','title','body'];

    public function node()
    {
        return $this->belongsTo(ContentNode::class, 'content_node_id');
    }
}
