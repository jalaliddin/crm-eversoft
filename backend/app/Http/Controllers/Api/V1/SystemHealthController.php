<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\System\HealthService;
use Illuminate\Http\JsonResponse;

class SystemHealthController extends Controller
{
    public function __invoke(HealthService $health): JsonResponse
    {
        return $this->ok($health->report());
    }

    /** Public liveness probe for load balancers: no details. */
    public function ping(HealthService $health): JsonResponse
    {
        $report = $health->report(includeDevices: false);

        return response()->json(['status' => $report['database']['status'] === 'ok' ? 'ok' : 'degraded'], $report['database']['status'] === 'ok' ? 200 : 503);
    }
}
