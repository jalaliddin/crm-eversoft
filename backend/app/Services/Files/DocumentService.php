<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\Document;
use App\Services\Crm\TimelineRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Storage abstraction for attachments. Files live on the configured disk (local now, S3-compatible later
 * by setting DOCUMENTS_DISK=s3); only metadata is stored in MySQL. Paths are namespaced per tenant and
 * files are served through an authorized endpoint, never from a public URL.
 */
class DocumentService
{
    public const ALLOWED_MIMES = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv', 'rtf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip',
    ];

    public const MAX_KILOBYTES = 20480;

    public function __construct(private readonly TimelineRecorder $timeline) {}

    public function store(Model $owner, UploadedFile $file): Document
    {
        $disk = (string) config('filesystems.documents_disk', 'local');
        $directory = sprintf('tenants/%d/documents/%s/%s', $owner->getAttribute('organization_id'), $owner->getMorphClass(), now()->format('Y/m'));
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs($directory, Str::uuid()->toString().'.'.$extension, ['disk' => $disk]);

        $document = Document::query()->create([
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
        $document->setRelation('documentable', $owner);

        if (in_array($owner->getMorphClass(), ['customer', 'lead', 'deal'], true)) {
            $this->timeline->record('document.uploaded', $document, 'Document uploaded: '.$document->original_name);
        }

        return $document;
    }

    public function download(Document $document): StreamedResponse
    {
        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(Document $document): void
    {
        $document->delete();
        Storage::disk($document->disk)->delete($document->path);
    }
}
