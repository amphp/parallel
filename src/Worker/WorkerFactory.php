<?php
namespace Icicle\Concurrent\Worker;

/**
 * The built-in worker factory type.
 */
class WorkerFactory implements WorkerFactoryInterface
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
        if (extension_loaded('pthreads')) {
            return new WorkerThread();
        }

        if (extension_loaded('pcntl')) {
            return new WorkerFork();
        }

        return new WorkerProcess();
    }
}
