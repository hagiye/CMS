<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'content_node_id' => $this->content_node_id === null ? null : (int) $this->content_node_id,
            'kind' => $this->kind,
            'title' => $this->title,
            'source' => $this->sourceType(),
            'url' => $this->publicUrl(),
            'path' => $this->path,
            'external_url' => $this->external_url,
            'page_start' => $this->page_start,
            'page_end' => $this->page_end,
            'checksum' => $this->checksum,
            'original_filename' => $this->original_filename,
            'imported_at' => $this->imported_at?->toISOString(),
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function publicUrl(): ?string
    {
        if (filled($this->path)) {
            return Storage::disk('public')->url($this->path);
        }

        return $this->external_url;
    }

    private function sourceType(): ?string
    {
        return match (true) {
            filled($this->path) && filled($this->external_url) => 'upload_and_external',
            filled($this->path) => 'upload',
            filled($this->external_url) => 'external',
            default => null,
        };
    }
}
