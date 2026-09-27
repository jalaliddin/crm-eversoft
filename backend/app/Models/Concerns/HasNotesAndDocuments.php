<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Document;
use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotesAndDocuments
{
    public function notesList(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest();
    }
}
