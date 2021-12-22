<?php

namespace Amp\Parallel\Worker\Internal;

abstract class JobPacket
{
    public function __construct(
        private string $id,
    ) {
    }

    /**
     * @return string Task identifier.
     */
    final public function getId(): string
    {
        return $this->id;
    }
}
