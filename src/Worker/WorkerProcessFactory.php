<?php
namespace Icicle\Concurrent\Worker;

/**
 * Worker factory for creating worker processes.
 */
class WorkerProcessFactory implements WorkerFactory
{
    /**
     * {@inheritdoc}
     */
    public function create()
    {
        return new WorkerProcess();
    }
}
