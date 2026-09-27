<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\CustomFieldEntity;
use App\Enums\LeadStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Crm\Customer;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Services\CustomFields\CustomFieldValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        private readonly TimelineRecorder $timeline,
        private readonly CustomFieldValidator $customFields,
        private readonly CustomerService $customers,
        private readonly DealService $deals,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Lead
    {
        return DB::transaction(function () use ($data, $user) {
            $lead = Lead::query()->create([
                ...Arr::except($data, ['tag_ids', 'custom_fields']),
                'status' => $data['status'] ?? LeadStatus::New->value,
                'custom_fields' => $this->customFields->validate(CustomFieldEntity::Lead, $data['custom_fields'] ?? null),
                'created_by' => $user->id,
            ]);

            if (isset($data['tag_ids'])) {
                $lead->syncTags($data['tag_ids']);
            }

            $this->timeline->record('lead.created', $lead, 'Lead created: '.$lead->name);
            $this->notifyAssignee($lead, null, $user);

            return $lead;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data, User $user): Lead
    {
        return DB::transaction(function () use ($lead, $data, $user) {
            $previousStatus = $lead->status;
            $previousAssignee = $lead->assigned_to;

            $attributes = Arr::except($data, ['tag_ids', 'custom_fields']);
            if (array_key_exists('custom_fields', $data)) {
                $attributes['custom_fields'] = [
                    ...(array) $lead->custom_fields,
                    ...$this->customFields->validate(CustomFieldEntity::Lead, $data['custom_fields'], partial: true),
                ];
            }

            if (($attributes['status'] ?? null) === LeadStatus::Converted->value && $previousStatus !== LeadStatus::Converted) {
                throw new BusinessRuleException('Use the convert action to convert a lead.', ['status' => ['Use the convert action.']]);
            }

            $lead->update($attributes);

            if (isset($data['tag_ids'])) {
                $lead->syncTags($data['tag_ids']);
            }

            if ($lead->status !== $previousStatus) {
                $this->timeline->record('lead.status_changed', $lead, sprintf('Lead status: %s → %s', $previousStatus->label(), $lead->status->label()), [
                    'from' => $previousStatus->value,
                    'to' => $lead->status->value,
                ]);
            }

            $this->notifyAssignee($lead, $previousAssignee, $user);

            return $lead;
        });
    }

    /** Kanban move. */
    public function move(Lead $lead, LeadStatus $status, int $position, User $user): Lead
    {
        if ($status === LeadStatus::Converted) {
            throw new BusinessRuleException('Use the convert action to convert a lead.');
        }

        return $this->update($lead, ['status' => $status->value, 'position' => $position], $user);
    }

    /**
     * Convert a lead into a customer (new or existing) and optionally a deal.
     *
     * @param  array{customer_id?: ?int, create_deal?: bool, deal_title?: ?string, deal_value?: ?float, pipeline_id?: ?int}  $data
     * @return array{lead: Lead, customer: Customer, deal: ?Deal}
     */
    public function convert(Lead $lead, array $data, User $user): array
    {
        if ($lead->status === LeadStatus::Converted) {
            throw new BusinessRuleException('This lead has already been converted.');
        }

        return DB::transaction(function () use ($lead, $data, $user) {
            $customer = ! empty($data['customer_id'])
                ? Customer::query()->findOrFail($data['customer_id'])
                : ($lead->customer ?? $this->customers->create($this->customerDataFrom($lead), $user));

            $deal = null;
            if (! empty($data['create_deal'])) {
                $deal = $this->deals->create([
                    'title' => ($data['deal_title'] ?? null) ?: $lead->name,
                    'value' => $data['deal_value'] ?? $lead->expected_value ?? 0,
                    'customer_id' => $customer->id,
                    'lead_id' => $lead->id,
                    'pipeline_id' => $data['pipeline_id'] ?? null,
                    'assigned_to' => $lead->assigned_to,
                ], $user);
            }

            $previous = $lead->status;
            $lead->update(['status' => LeadStatus::Converted, 'customer_id' => $customer->id, 'converted_at' => now()]);

            $this->timeline->record('lead.status_changed', $lead, 'Lead converted to customer '.$customer->display_name, [
                'from' => $previous->value,
                'to' => LeadStatus::Converted->value,
                'deal_id' => $deal?->id,
            ]);

            return ['lead' => $lead, 'customer' => $customer, 'deal' => $deal];
        });
    }

    /** @return array<string, mixed> */
    private function customerDataFrom(Lead $lead): array
    {
        $names = preg_split('/\s+/', trim($lead->name), 2) ?: [$lead->name];

        return [
            'type' => $lead->company_name ? CustomerType::Company->value : CustomerType::Individual->value,
            'first_name' => $names[0] ?? null,
            'last_name' => $names[1] ?? null,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'source' => $lead->source,
            'status' => CustomerStatus::Active->value,
            'owner_id' => $lead->assigned_to,
        ];
    }

    private function notifyAssignee(Lead $lead, ?int $previousAssignee, User $by): void
    {
        if ($lead->assigned_to === null || $lead->assigned_to === $previousAssignee || $lead->assigned_to === $by->id) {
            return;
        }

        $assignee = User::query()->inTenant($lead->organization_id)->find($lead->assigned_to);
        $assignee?->notify(new LeadAssignedNotification($lead->organization_id, [
            'lead' => $lead->name,
            'lead_id' => $lead->id,
            'by' => $by->name,
            'link' => '/crm/leads/'.$lead->id,
        ]));
    }
}
