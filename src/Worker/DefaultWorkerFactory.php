<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Forking\Fork;
use Icicle\Concurrent\Threading\Thread;

/**
 * The built-in worker factory type.
 */
class DefaultWorkerFactory implements WorkerFactory
{
    /**
     * {@inheritdoc}
     *
     * The type of worker created depends on the extensions available. If multi-threading is enabled, a WorkerThread
     * will be created. If threads are not available, a WorkerFork will be created if forking is available, otherwise
     * a WorkerProcess will be created.
     */
    public function create()
    {
        if (Thread::enabled()) {
            return new WorkerThread();
        }

        if (Fork::enabled()) {
            return new WorkerFork();
        }

        return new WorkerProcess();
    }
}
