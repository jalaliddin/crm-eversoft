<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\Validation\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->ok(TagResource::collection(Tag::query()->orderBy('name')->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', TenantRule::unique('tags', 'name', softDeletes: false)],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        return $this->created(TagResource::make(Tag::query()->create(['name' => $data['name'], 'color' => $data['color'] ?? 'gray'])));
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', TenantRule::unique('tags', 'name', $tag->id, softDeletes: false)],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        $tag->update($data);

        return $this->ok(TagResource::make($tag), 'Tag updated.');
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return $this->ok(null, 'Tag deleted.');
    }
}
