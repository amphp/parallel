<?php

namespace Amp\Concurrent\Worker\Internal;

use Amp\Concurrent\TaskException;
use Amp\Failure;
use Interop\Async\Awaitable;

class TaskFailure implements TaskResult {
    /** @var string */
    private $id;
    
    /** @var string */
    private $type;

    /** @var string */
    private $message;

    /** @var int */
    private $code;

    /** @var array */
    private $trace;

    public function __construct(string $id, \Throwable $exception) {
        $this->id = $id;
        $this->type = get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();
    }

    public function getId(): string {
        return $this->id;
    }
    
    public function getAwaitable(): Awaitable {
        return new Failure(new TaskException(
            sprintf('Uncaught exception in worker of type "%s" with message "%s"', $this->type, $this->message),
            $this->code,
            $this->trace
        ));
    }
}