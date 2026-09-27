<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    /** Instants are always emitted as UTC ISO-8601; the client renders them in the tenant timezone. */
    protected function iso(?DateTimeInterface $value): ?string
    {
        return $value === null ? null : CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    protected function day(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    /** @return array{id: int, name: string}|null */
    protected function userRef($user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
