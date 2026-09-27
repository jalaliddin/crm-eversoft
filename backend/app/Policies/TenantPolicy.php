<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy: permission check + same-organization check.
 * The global tenant scope already hides foreign records; this is defense in depth
 * for models loaded outside the scope (relations, jobs, withoutGlobalScopes()).
 */
abstract class TenantPolicy
{
    /** Permission prefix, e.g. "customers" → customers.view / customers.update / customers.delete. */
    abstract protected function prefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->prefix().'.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasPermission($this->prefix().'.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->prefix().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasPermission($this->prefix().'.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model) && $user->hasPermission($this->prefix().'.delete');
    }

    protected function sameTenant(User $user, Model $model): bool
    {
        return $user->organization_id !== null && (int) $model->getAttribute('organization_id') === $user->organization_id;
    }

    /** Owner-based visibility for CRM records (assigned or created by the user) unless crm.view_all. */
    protected function visible(User $user, Model $model): bool
    {
        return $user->hasPermission('crm.view_all')
            || (int) $model->getAttribute('assigned_to') === $user->id
            || (int) $model->getAttribute('created_by') === $user->id;
    }
}
