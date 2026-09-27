<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\Crm\TimelineRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NoteController extends Controller
{
    public function __construct(private readonly TimelineRecorder $timeline) {}

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $owner = AttachableResolver::resolve($type, $id, $request->user(), false);

        return $this->ok(NoteResource::collection($owner->notesList()->with('author:id,name')->get()));
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $owner = AttachableResolver::resolve($type, $id, $request->user(), true);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        $note = $owner->notesList()->create(['body' => $data['body'], 'created_by' => $request->user()->id]);
        $note->setRelation('notable', $owner);

        if (in_array($type, ['customers', 'leads', 'deals'], true)) {
            $this->timeline->record('note.created', $note, 'Note added: '.Str::limit(strip_tags($data['body']), 120));
        }

        return $this->created(NoteResource::make($note->load('author:id,name')));
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->created_by === $request->user()->id, 403, 'Only the author can edit a note.');
        $note->update($request->validate(['body' => ['required', 'string', 'max:20000']]));

        return $this->ok(NoteResource::make($note->load('author:id,name')), 'Note updated.');
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $owner = $note->notable;
        abort_if($owner === null, 404);
        abort_unless($note->created_by === $request->user()->id || $request->user()->can('update', $owner), 403);
        $note->delete();

        return $this->ok(null, 'Note deleted.');
    }
}
