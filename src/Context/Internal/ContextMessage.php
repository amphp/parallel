<?php

namespace Amp\Parallel\Context\Internal;

/** @internal */
final class ContextMessage
{
    public function __construct(
        private readonly mixed $message,
    ) {
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }
}
