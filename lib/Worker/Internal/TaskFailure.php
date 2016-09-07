<?php declare(strict_types = 1);

namespace Amp\Parallel\Worker\Internal;

use Amp\Failure;
use Amp\Parallel\TaskException;
use Interop\Async\Awaitable;

class TaskFailure extends TaskResult {
    /** @var string */
    private $type;

    /** @var string */
    private $message;

    /** @var int */
    private $code;

    /** @var array */
    private $trace;

    public function __construct(string $id, \Throwable $exception) {
        parent::__construct($id);
        $this->type = \get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();
    }
    
    public function getAwaitable(): Awaitable {
        return new Failure(new TaskException(
            $this->type,
            sprintf('Uncaught exception in worker of type "%s" with message "%s"', $this->type, $this->message),
            $this->code,
            $this->trace
        ));
    }
}