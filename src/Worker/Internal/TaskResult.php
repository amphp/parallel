<?php

namespace Amp\Parallel\Worker\Internal;

/**
 * @internal
 *
 * @template T
 */
abstract class TaskResult extends JobPacket
{
    /**
     * @return T Resolved with the task result or failure reason.
     */
    abstract public function getResult(): mixed;
}
