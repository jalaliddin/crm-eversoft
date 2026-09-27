<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown when code attempts to write a record into a tenant other than the active one. */
class TenantMismatchException extends RuntimeException
{
    public function __construct(string $message = 'Cross-tenant write rejected.')
    {
        parent::__construct($message);
    }
}
