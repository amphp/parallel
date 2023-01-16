<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

/**
 * @psalm-type FlattenedTrace = list<array<non-empty-string, scalar|list<scalar>>>
 */
final class ContextPanicError extends \Error
{
    use Internal\ContextException;

    protected function invokeExceptionConstructor(string $message, ?\Throwable $previous): void
    {
        parent::__construct($message, 0, $previous);
    }
}
