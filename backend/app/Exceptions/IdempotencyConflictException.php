<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when:
 *  - expected_week mismatches the server's next playable week (§4.5.5)
 *  - expected_version mismatches matches.version (optimistic lock, §4.5.4)
 *
 * Maps to HTTP 409 Conflict.
 */
class IdempotencyConflictException extends RuntimeException
{
    public function __construct(string $message = 'Conflict: expected state no longer matches.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
