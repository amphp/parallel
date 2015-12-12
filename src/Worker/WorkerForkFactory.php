<?php
namespace Icicle\Concurrent\Worker;

/**
 * Worker factory for creating worker forks.
 */
class WorkerForkFactory implements WorkerFactory
{
    /**
     * {@inheritdoc}
     */
    public function create()
    {
        return new WorkerFork();
    }
}
