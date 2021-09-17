<?php

namespace Amp\Parallel\Sync;

final class ExitFailure implements ExitResult
{
    private string $type;

    private string $message;

    private int|string $code;

    /** @var string[] */
    private array $trace;

    private ?self $previous = null;

    public function __construct(\Throwable $exception)
    {
        $this->type = \get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = flattenThrowableBacktrace($exception);

        if ($previous = $exception->getPrevious()) {
            $this->previous = new self($previous);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(): mixed
    {
        throw $this->createException();
    }

    private function createException(): ContextPanicError
    {
        $previous = $this->previous?->createException();

        return new ContextPanicError($this->type, $this->message, $this->code, $this->trace, $previous);
    }
}
