<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Parallel\Worker\TaskFailureError;
use Amp\Parallel\Worker\TaskFailureException;
use Amp\Parallel\Worker\TaskFailureThrowable;
use function Amp\Parallel\Context\flattenThrowableBacktrace;

/**
 * @internal
 *
 * @template-extends TaskResult<never>
 */
class TaskFailure extends TaskResult
{
    private const PARENT_EXCEPTION = 0;
    private const PARENT_ERROR = 1;

    private readonly string $type;

    private readonly int $parent;

    private readonly string $message;

    private readonly string|int $code;

    /** @var string[] */
    private readonly array $trace;

    private readonly ?self $previous;

    public function __construct(string $id, \Throwable $exception)
    {
        parent::__construct($id);
        $this->type = \get_class($exception);
        $this->parent = $exception instanceof \Error ? self::PARENT_ERROR : self::PARENT_EXCEPTION;
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = flattenThrowableBacktrace($exception);

        $previous = $exception->getPrevious();
        $this->previous = $previous ? new self($id, $previous) : null;
    }

    /**
     * @throws TaskFailureThrowable
     */
    public function getResult(): never
    {
        throw $this->createException();
    }

    final protected function createException(): TaskFailureThrowable
    {
        $previous = $this->previous?->createException();

        if ($this->parent === self::PARENT_ERROR) {
            return new TaskFailureError($this->type, $this->message, $this->code, $this->trace, $previous);
        }

        return new TaskFailureException($this->type, $this->message, $this->code, $this->trace, $previous);
    }
}
