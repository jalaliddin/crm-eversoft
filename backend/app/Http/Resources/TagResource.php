<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;

/** @mixin Tag */
class TagResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'color' => $this->color];
    }
}
