<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Threading\Thread;

/**
 * The built-in worker factory type.
 */
class DefaultWorkerFactory implements WorkerFactory {
    /**
     * {@inheritdoc}
     *
     * The type of worker created depends on the extensions available. If multi-threading is enabled, a WorkerThread
     * will be created. If threads are not available a WorkerProcess will be created.
     *
     * \Amp\Parallel\Forking\WorkerFork is not used in the default factory since forking a process for workers is very
     * sensitive to timing and process state and should be used only by the application designer if desired.
     */
    public function create(): Worker {
        if (Thread::supported()) {
            return new WorkerThread;
        }

        return new WorkerProcess;
    }
}
