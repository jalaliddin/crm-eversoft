<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DealPolicy extends TenantPolicy
{
    protected function prefix(): string
    {
        return 'deals';
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $this->visible($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->visible($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->visible($user, $model);
    }
}
