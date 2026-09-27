<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Generic audit trail. Secrets are stripped before anything is persisted. */
class AuditLogger
{
    /** Attribute names that must never reach the audit log. */
    private const SENSITIVE = [
        'password', 'remember_token', 'encrypted_password', 'bridge_token_hash', 'token', 'api_token',
        'secret', 'current_password', 'password_confirmation',
    ];

    public function __construct(private readonly TenantContext $tenant, private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function modelEvent(string $action, Model $model, array $old, array $new): void
    {
        $exclude = method_exists($model, 'auditExclude') ? $model->auditExclude() : [];

        $this->log(
            event: $action,
            auditable: $model,
            old: $this->clean($old, $exclude),
            new: $this->clean($new, $exclude),
            organizationId: $model->getAttribute('organization_id') ?? $this->tenant->id(),
        );
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        array $old = [],
        array $new = [],
        ?int $organizationId = null,
        ?User $user = null,
    ): void {
        $user ??= Auth::user();

        AuditLog::withoutGlobalScopes()->create([
            'organization_id' => $organizationId ?? $this->tenant->id() ?? $user?->organization_id,
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'ip_address' => app()->runningInConsole() ? null : $this->request->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) $this->request->userAgent(), 0, 512),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $extraExclude
     * @return array<string, mixed>
     */
    private function clean(array $values, array $extraExclude = []): array
    {
        $blocked = array_merge(self::SENSITIVE, $extraExclude, ['created_at', 'updated_at']);

        return array_filter(
            $values,
            fn ($value, $key) => ! in_array($key, $blocked, true) && ! str_contains((string) $key, 'password'),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
