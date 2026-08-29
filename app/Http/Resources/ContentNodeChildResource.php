<?php

namespace App\Http\Resources;

use App\Enums\ContentNodeStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentNodeChildResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->relationLoaded('translations')
            ? $this->translations->first()
            : null;

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'slug' => $this->slug,
            'type' => $this->type,
            'position' => $this->position,
            'status' => $this->status instanceof ContentNodeStatus ? $this->status->value : $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'edition' => $this->edition,
            'source_page_start' => $this->source_page_start,
            'source_page_end' => $this->source_page_end,
            'revision' => $this->revision,
            'title' => $translation?->title,
            'body' => $translation?->body,
            'meta' => $this->meta,
        ];
    }
}
