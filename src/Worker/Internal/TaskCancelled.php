<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\CancelledException;
use Amp\Parallel\Worker\TaskCancelledException;

/** @internal */
final class TaskCancelled extends TaskFailure
{
    public function __construct(string $id, CancelledException $exception)
    {
        parent::__construct($id, $exception);
    }

    /**
     * @return never
     * @throws TaskCancelledException
     */
    public function getResult(): mixed
    {
        throw new TaskCancelledException($this->createException());
    }
}
