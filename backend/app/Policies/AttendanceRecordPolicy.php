<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecordPolicy extends TenantPolicy
{
    protected function prefix(): string
    {
        return 'attendance';
    }

    public function view(User $user, Model $model): bool
    {
        if (! $this->sameTenant($user, $model)) {
            return false;
        }

        return $user->hasPermission('attendance.view')
            || ($user->hasPermission('attendance.view_own') && $user->employee?->id === (int) $model->getAttribute('employee_id'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasPermission('attendance.correct');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}
