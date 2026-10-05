<?php

namespace App\Http\Resources\Admin;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'kind' => $this->kind->value,
            'mime_type' => $this->mime_type,
            'size' => $this->humanSize(),
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'is_decorative' => $this->is_decorative,
            'thumbnail' => $this->isImage() ? $this->thumbnailUrl(320) : null,
            'url' => $this->url(),
            'edit_url' => route('admin.media.edit', $this->resource),
        ];
    }
}
