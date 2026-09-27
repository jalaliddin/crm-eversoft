<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\Files\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $owner = AttachableResolver::resolve($type, $id, $request->user(), false);

        return $this->ok(DocumentResource::collection($owner->documents()->with('uploader:id,name')->get()));
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $owner = AttachableResolver::resolve($type, $id, $request->user(), true);
        $request->validate([
            // Extension and real MIME type (sniffed from content) must both be in the allow-list.
            'file' => ['required', 'file', 'max:'.DocumentService::MAX_KILOBYTES, 'mimes:'.implode(',', DocumentService::ALLOWED_MIMES)],
        ]);

        $document = $this->documents->store($owner, $request->file('file'));

        return $this->created(DocumentResource::make($document->load('uploader:id,name')));
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $owner = $document->documentable;
        abort_if($owner === null, 404);
        abort_unless($request->user()->can('view', $owner), 403);

        return $this->documents->download($document);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $owner = $document->documentable;
        abort_if($owner === null, 404);
        abort_unless($request->user()->can('update', $owner), 403);
        $this->documents->delete($document);

        return $this->ok(null, 'Document deleted.');
    }
}
