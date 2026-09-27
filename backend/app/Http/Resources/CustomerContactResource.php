<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Crm\CustomerContact;
use Illuminate\Http\Request;

/** @mixin CustomerContact */
class CustomerContactResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'position' => $this->position,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_primary' => $this->is_primary,
        ];
    }
}
