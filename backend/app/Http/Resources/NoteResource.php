<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;

/** @mixin Note */
class NoteResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->userRef($this->whenLoaded('author', fn () => $this->author, null)),
            'created_by' => $this->created_by,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
