<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->with('user:id,name')
            ->when($request->query('event'), fn ($q, $v) => $q->where('event', 'like', $v.'%'))
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('auditable_type'), fn ($q, $v) => $q->where('auditable_type', $v))
            ->when($request->query('auditable_id'), fn ($q, $v) => $q->where('auditable_id', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request, 50)), AuditLogResource::class);
    }
}
