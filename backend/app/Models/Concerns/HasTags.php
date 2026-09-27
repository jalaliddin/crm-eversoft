<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** @param list<int> $tagIds already validated to belong to the tenant */
    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }
}
