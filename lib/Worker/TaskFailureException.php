<?php

namespace Amp\Parallel\Worker;

use function Amp\Parallel\Sync\formatFlattenedBacktrace;

final class TaskFailureException extends \Exception implements TaskFailureThrowable
{
    /**
     * @param string $className Original exception class name.
     * @param string $originalMessage Original exception message.
     * @param int|string $originalCode Original exception code.
     * @param array $originalTrace Backtrace generated by {@see flattenThrowableBacktrace()}.
     * @param TaskFailureThrowable|null $previous Instance representing any previous exception thrown in the Task.
     */
    public function __construct(
        private string $className,
        private string $originalMessage,
        private int|string $originalCode,
        private array $originalTrace,
        ?TaskFailureThrowable $previous = null
    ) {
        $format = 'Uncaught %s in worker with message "%s" and code "%s"; use %s::getOriginalTrace() '
            . 'for the stack trace in the worker';

        parent::__construct(
            \sprintf($format, $className, $originalMessage, $originalCode, self::class),
            $originalCode,
            $previous
        );
    }

    /**
     * @return string Original exception class name.
     */
    public function getOriginalClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string Original exception message.
     */
    public function getOriginalMessage(): string
    {
        return $this->originalMessage;
    }

    /**
     * @return int|string Original exception code.
     */
    public function getOriginalCode(): string|int
    {
        return $this->originalCode;
    }

    /**
     * Returns the original exception stack trace.
     *
     * @return array Same as {@see Throwable::getTrace()}, except all function arguments are formatted as strings.
     */
    public function getOriginalTrace(): array
    {
        return $this->originalTrace;
    }

    /**
     * Original backtrace flattened to a human-readable string.
     *
     * @return string
     */
    public function getOriginalTraceAsString(): string
    {
        return formatFlattenedBacktrace($this->originalTrace);
    }
}
