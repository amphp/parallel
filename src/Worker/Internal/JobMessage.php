<?php

namespace Amp\Parallel\Worker\Internal;

/** @internal */
final class JobMessage extends JobPacket
{
    public function __construct(
        string $id,
        private mixed $message,
    ) {
        parent::__construct($id);
    }

    public function getMessage(): mixed
    {
        return $this->message;
    }
}
