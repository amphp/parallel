<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Parallel\Sync;
use Amp\Parallel\Worker\TaskFailureError;
use Amp\Parallel\Worker\TaskFailureException;
use Amp\Parallel\Worker\TaskFailureThrowable;

/** @internal */
class TaskFailure extends TaskResult
{
    private const PARENT_EXCEPTION = 0;
    private const PARENT_ERROR = 1;

    /** @var string */
    private string $type;

    /** @var int */
    private int $parent;

    /** @var string */
    private string $message;

    /** @var int|string */
    private string|int $code;

    /** @var string[] */
    private array $trace;

    /** @var self|null */
    private ?self $previous = null;

    public function __construct(string $id, \Throwable $exception)
    {
        parent::__construct($id);
        $this->type = \get_class($exception);
        $this->parent = $exception instanceof \Error ? self::PARENT_ERROR : self::PARENT_EXCEPTION;
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = Sync\flattenThrowableBacktrace($exception);

        if ($previous = $exception->getPrevious()) {
            $this->previous = new self($id, $previous);
        }
    }

    /**
     * @return never
     * @throws TaskFailureThrowable
     */
    public function getResult(): mixed
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
