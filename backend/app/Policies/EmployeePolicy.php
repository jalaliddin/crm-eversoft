<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EmployeePolicy extends TenantPolicy
{
    protected function prefix(): string
    {
        return 'employees';
    }

    /** Employees may always open their own profile. */
    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model)
            || ($this->sameTenant($user, $model) && (int) $model->getAttribute('user_id') === $user->id);
    }
}
