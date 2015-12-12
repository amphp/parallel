<?php
namespace Icicle\Concurrent\Worker;

/**
 * Worker factory for creating worker threads.
 */
class WorkerThreadFactory implements WorkerFactory
{
    /**
     * {@inheritdoc}
     */
    public function create()
    {
        return new WorkerThread();
    }
}
