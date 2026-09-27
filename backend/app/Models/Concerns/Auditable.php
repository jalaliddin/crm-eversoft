<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes create/update/delete entries to the generic audit log.
 * Models can list extra attributes to never log in $auditExclude.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditLogger::class)->modelEvent('created', $model, [], $model->getAttributes()));

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditLogger::class)->modelEvent('updated', $model, $old, $changes);
        });

        static::deleted(fn (Model $model) => app(AuditLogger::class)->modelEvent('deleted', $model, $model->getAttributes(), []));
    }

    /** @return list<string> */
    public function auditExclude(): array
    {
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }
}
