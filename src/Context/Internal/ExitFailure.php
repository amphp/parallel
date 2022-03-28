<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Parallel\Context\ContextPanicError;
use function Amp\Parallel\Context\flattenThrowableBacktrace;

/**
 * @internal
 * @template-implements ExitResult<never>
 */
final class ExitFailure implements ExitResult
{
    private readonly string $type;

    private readonly string $message;

    private readonly int|string $code;

    /** @var string[] */
    private readonly array $trace;

    private readonly ?self $previous;

    public function __construct(\Throwable $exception)
    {
        $this->type = \get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = flattenThrowableBacktrace($exception);

        $previous = $exception->getPrevious();
        $this->previous = $previous ? new self($previous) : null;
    }

    /**
     * @throws ContextPanicError
     */
    public function getResult(): never
    {
        throw $this->createException();
    }

    private function createException(): ContextPanicError
    {
        $previous = $this->previous?->createException();

        return new ContextPanicError($this->type, $this->message, $this->code, $this->trace, $previous);
    }
}
