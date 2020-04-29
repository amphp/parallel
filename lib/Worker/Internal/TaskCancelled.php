<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\CancelledException;
use Amp\Failure;
use Amp\Parallel\Worker\TaskCancelledException;
use Amp\Promise;

/** @internal */
final class TaskCancelled extends TaskFailure
{
    public function __construct(string $id, CancelledException $exception)
    {
        parent::__construct($id, $exception);
    }

    public function promise(): Promise
    {
        return new Failure(new TaskCancelledException($this->createException()));
    }
}
