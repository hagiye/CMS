<?php

namespace App\Http\Resources;

use App\Enums\ContentNodeStatus;
use App\Support\LocalePreference;
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
            ? LocalePreference::select($this->translations, $request->query('locale'))
            : null;
        $children = $this->relationLoaded('publicChildren')
            ? $this->publicChildren
            : ($this->relationLoaded('children') ? $this->children : collect());

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
            'source_document_id' => $this->source_document_id,
            'revision' => $this->revision,
            'locale' => $translation?->locale,
            'title' => $translation?->title,
            'body' => $translation?->body,
            'meta' => $this->meta,
            'children' => self::collection($children),
        ];
    }
}
