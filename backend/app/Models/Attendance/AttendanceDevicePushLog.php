<?php

declare(strict_types=1);

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One request received from a terminal in HTTP-push mode. Kept 14 days (pruned daily). */
class AttendanceDevicePushLog extends Model
{
    use BelongsToOrganization, MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'device_id', 'status', 'event_type', 'events_count', 'stored_count', 'summary',
        'content_type', 'remote_ip', 'body_excerpt', 'error',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('created_at', '<', now()->subDays(14));
    }
}
