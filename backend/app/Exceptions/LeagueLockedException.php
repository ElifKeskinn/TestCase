<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a mutation is attempted while LeagueSettings.status is
 * 'running' or 'resetting'. Maps to HTTP 423 Locked (§4.5.8).
 */
class LeagueLockedException extends RuntimeException
{
    public function __construct(string $message = 'League is currently locked by another operation.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
