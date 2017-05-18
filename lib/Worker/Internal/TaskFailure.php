<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Failure;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\TaskException;
use Amp\Promise;

class TaskFailure extends TaskResult {
    const PARENT_EXCEPTION = 0;
    const PARENT_ERROR = 1;

    /** @var string */
    private $type;

    /** @var int */
    private $parent;

    /** @var string */
    private $message;

    /** @var int */
    private $code;

    /** @var array */
    private $trace;

    public function __construct(string $id, \Throwable $exception) {
        parent::__construct($id);
        $this->type = \get_class($exception);
        $this->parent = $exception instanceof \Error ? self::PARENT_ERROR : self::PARENT_EXCEPTION;
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();
    }
    
    public function promise(): Promise {
        switch ($this->parent) {
            case self::PARENT_ERROR:
                $exception = new TaskError(
                    $this->type,
                    sprintf('Uncaught Error in worker of type "%s" with message "%s"', $this->type, $this->message),
                    $this->code,
                    $this->trace
                );
                break;

            default:
                $exception = new TaskException(
                    $this->type,
                    sprintf('Uncaught Exception in worker of type "%s" with message "%s"', $this->type, $this->message),
                    $this->code,
                    $this->trace
                );
        }

        return new Failure($exception);
    }
}