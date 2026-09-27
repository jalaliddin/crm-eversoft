<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Domain rule violation that should be shown to the user as a 422. */
class BusinessRuleException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
