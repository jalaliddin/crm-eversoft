<?php

declare(strict_types=1);

namespace App\Policies;

class CustomerPolicy extends TenantPolicy
{
    protected function prefix(): string
    {
        return 'customers';
    }
}
