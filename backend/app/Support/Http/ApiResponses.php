<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/** Uniform envelope: {success, data, message} / {success, data, meta} / {success, message, errors}. */
trait ApiResponses
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
            'message' => $message,
        ], $status);
    }

    protected function created(mixed $data = null, ?string $message = 'Created.'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    /** @param class-string<JsonResource> $resource */
    protected function paginated(LengthAwarePaginator $paginator, string $resource, array $extraMeta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resource::collection($paginator->getCollection())->resolve(request()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                ...$extraMeta,
            ],
        ]);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => (object) $errors], $status);
    }
}
