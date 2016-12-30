<?php

namespace Amp\Parallel;

class ChannelException extends \Exception {
    public function __construct(string $message, \Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
    }
}
