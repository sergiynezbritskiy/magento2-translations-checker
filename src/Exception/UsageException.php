<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Exception;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Throwable;

/**
 * Thrown for invalid invocations (missing or wrong options, unusable environment).
 *
 * Symfony Console renders uncaught exceptions and uses their code as the process exit
 * status, so the code is fixed to Command::INVALID (2) -- the same value the sibling
 * checkers use for usage errors.
 */
final class UsageException extends InvalidArgumentException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, Command::INVALID, $previous);
    }
}
