<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\CustomFieldEntity;
use App\Models\Crm\Customer;
use App\Models\User;
use App\Services\CustomFields\CustomFieldValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly CustomFieldValidator $customFields,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Customer
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = Customer::query()->create([
                ...Arr::except($data, ['tag_ids', 'custom_fields']),
                'custom_fields' => $this->customFields->validate(CustomFieldEntity::Customer, $data['custom_fields'] ?? null),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (isset($data['tag_ids'])) {
                $customer->syncTags($data['tag_ids']);
            }

            $this->timeline->record('customer.created', $customer, 'Customer created: '.$customer->display_name);

            return $customer;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Customer $customer, array $data, User $user): Customer
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $attributes = Arr::except($data, ['tag_ids', 'custom_fields']);
            if (array_key_exists('custom_fields', $data)) {
                $attributes['custom_fields'] = [
                    ...(array) $customer->custom_fields,
                    ...$this->customFields->validate(CustomFieldEntity::Customer, $data['custom_fields'], partial: true),
                ];
            }

            $customer->fill([...$attributes, 'updated_by' => $user->id]);
            $changed = array_keys(Arr::except($customer->getDirty(), ['updated_by']));
            $customer->save();

            if (isset($data['tag_ids'])) {
                $customer->syncTags($data['tag_ids']);
            }

            if ($changed !== []) {
                $this->timeline->record('customer.updated', $customer, 'Customer updated', ['fields' => $changed]);
            }

            return $customer;
        });
    }
}
