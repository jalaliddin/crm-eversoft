<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Hr\Holiday;
use Illuminate\Http\Request;

/** @mixin Holiday */
class HolidayResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'date' => $this->day($this->date), 'name' => $this->name];
    }
}
