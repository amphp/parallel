<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\{ Forking\Fork, Threading\Thread };

/**
 * The built-in worker factory type.
 */
class DefaultWorkerFactory implements WorkerFactory {
    /**
     * {@inheritdoc}
     *
     * The type of worker created depends on the extensions available. If multi-threading is enabled, a WorkerThread
     * will be created. If threads are not available, a WorkerFork will be created if forking is available, otherwise
     * a WorkerProcess will be created.
     */
    public function create(): Worker {
        if (Thread::supported()) {
            return new WorkerThread;
        }

        if (Fork::supported()) {
            return new WorkerFork;
        }

        return new WorkerProcess;
    }
}
