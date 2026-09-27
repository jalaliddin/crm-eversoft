<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipients
{
    /** Active users of an organization holding a permission. @return Collection<int, User> */
    public function withPermission(int $organizationId, string $permission): Collection
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->where('status', UserStatus::Active->value)
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('role_user')
                ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
                ->whereColumn('role_user.user_id', 'users.id')
                ->where('permission_role.permission', $permission))
            ->get();
    }
}
