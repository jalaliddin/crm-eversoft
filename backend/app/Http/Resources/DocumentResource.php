<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;

/** @mixin Document */
class DocumentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploader' => $this->userRef($this->whenLoaded('uploader', fn () => $this->uploader, null)),
            'download_url' => '/api/v1/documents/'.$this->id.'/download',
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
