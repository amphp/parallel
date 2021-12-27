<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Cancellation;
use Amp\Parallel\Worker\Job;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;

/** @internal */
final class PooledWorker implements Worker
{
    /**
     * @param Worker $worker
     * @param Closure(Worker):void $push Callable to push the worker back into the queue.
     */
    public function __construct(
        private Worker $worker,
        private \Closure $push,
    ) {
    }

    /**
     * Automatically pushes the worker back into the queue.
     */
    public function __destruct()
    {
        ($this->push)($this->worker);
    }

    public function isRunning(): bool
    {
        return $this->worker->isRunning();
    }

    public function isIdle(): bool
    {
        return $this->worker->isIdle();
    }

    public function enqueue(Task $task, ?Cancellation $cancellation = null): Job
    {
        $job = $this->worker->enqueue($task, $cancellation);

        // Retain a reference to $this to prevent premature release of worker.
        $future = $job->getFuture()->finally(fn () => $this);
        return $job->withFuture($future);
    }

    public function shutdown(): void
    {
        $this->worker->shutdown();
    }

    public function kill(): void
    {
        $this->worker->kill();
    }
}
