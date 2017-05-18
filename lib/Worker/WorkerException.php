<?php

namespace Amp\Parallel\Worker;

class WorkerException extends \Exception {
    /**
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, \Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
    }
}
