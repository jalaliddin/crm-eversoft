<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    public function __construct(string $message = 'Organization context is not resolved.')
    {
        parent::__construct($message);
    }
}
