<?php

namespace Amp\Parallel\Context\Internal;

final class ContextMessage
{
    public function __construct(
        private mixed $message,
    ) {
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }
}
