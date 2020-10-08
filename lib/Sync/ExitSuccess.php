<?php

namespace Amp\Parallel\Sync;

final class ExitSuccess implements ExitResult
{
    /** @var mixed */
    private mixed $result;

    public function __construct(mixed $result)
    {
        $this->result = $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(): mixed
    {
        return $this->result;
    }
}
