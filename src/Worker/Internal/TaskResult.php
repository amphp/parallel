<?php

namespace Amp\Parallel\Worker\Internal;

/**
 * @internal
 *
 * @template T
 */
abstract class TaskResult
{
    /** @var string Task identifier. */
    private string $id;

    /**
     * @param string $id Task identifier.
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string Task identifier.
     */
    final public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return T Resolved with the task result or failure reason.
     */
    abstract public function getResult(): mixed;
}
