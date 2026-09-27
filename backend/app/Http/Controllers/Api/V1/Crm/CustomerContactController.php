<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerContactResource;
use App\Models\Crm\Customer;
use App\Models\Crm\CustomerContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerContactController extends Controller
{
    public function index(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->ok(CustomerContactResource::collection($customer->contacts()->orderByDesc('is_primary')->orderBy('first_name')->get()));
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        $data = $this->validated($request);

        $contact = DB::transaction(function () use ($customer, $data) {
            if (! empty($data['is_primary'])) {
                $customer->contacts()->update(['is_primary' => false]);
            }

            return $customer->contacts()->create($data);
        });

        return $this->created(CustomerContactResource::make($contact));
    }

    public function update(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorize('update', $customer);
        abort_unless($contact->customer_id === $customer->id, 404);
        $data = $this->validated($request);

        DB::transaction(function () use ($customer, $contact, $data): void {
            if (! empty($data['is_primary'])) {
                $customer->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }
            $contact->update($data);
        });

        return $this->ok(CustomerContactResource::make($contact), 'Contact updated.');
    }

    public function destroy(Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorize('update', $customer);
        abort_unless($contact->customer_id === $customer->id, 404);
        $contact->delete();

        return $this->ok(null, 'Contact deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
    }
}
