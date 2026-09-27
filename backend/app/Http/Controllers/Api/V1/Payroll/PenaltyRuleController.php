<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Enums\PenaltyCalculation;
use App\Enums\PenaltyType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PenaltyRuleResource;
use App\Models\Payroll\PenaltyRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenaltyRuleController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->ok(PenaltyRuleResource::collection(PenaltyRule::query()->orderBy('type')->orderBy('min_minutes')->get()));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->created(PenaltyRuleResource::make(PenaltyRule::query()->create($this->validated($request))));
    }

    public function update(Request $request, PenaltyRule $penaltyRule): JsonResponse
    {
        $penaltyRule->update($this->validated($request));

        return $this->ok(PenaltyRuleResource::make($penaltyRule), 'Rule updated. Existing approved penalties are not changed.');
    }

    public function destroy(PenaltyRule $penaltyRule): JsonResponse
    {
        $penaltyRule->delete();

        return $this->ok(null, 'Rule deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PenaltyType::class)->except([PenaltyType::Manual])],
            'min_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_minutes' => ['nullable', 'integer', 'gte:min_minutes', 'max:1440'],
            'calculation' => ['required', Rule::enum(PenaltyCalculation::class)],
            'amount' => ['required_if:calculation,fixed,per_minute', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'percentage' => ['required_if:calculation,percent_of_daily', 'nullable', 'numeric', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['amount'] ??= 0;

        return $data;
    }
}
