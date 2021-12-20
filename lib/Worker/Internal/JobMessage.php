<?php

namespace Amp\Parallel\Worker\Internal;

final class JobMessage
{
    public function __construct(
        private string $id,
        private mixed $message,
    ) {
    }

    /**
     * @return string Task identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }
}
