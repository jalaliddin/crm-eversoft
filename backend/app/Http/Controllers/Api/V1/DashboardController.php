<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\CrmDashboardService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CrmDashboardService $dashboard, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $today = CarbonImmutable::now($tenant->timezone());
        $from = $data['from'] ?? $today->startOfMonth()->format('Y-m-d');
        $to = $data['to'] ?? $today->format('Y-m-d');

        abort_if(CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 731, 422, 'Date range is limited to two years.');

        return $this->ok($dashboard->build($request->user(), $from, $to));
    }
}
