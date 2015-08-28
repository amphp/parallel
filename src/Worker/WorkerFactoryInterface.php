<?php
namespace Icicle\Concurrent\Worker;

/**
 * Interface for factories used to create new workers.
 */
interface WorkerFactoryInterface
{
    /**
     * Creates a new worker instance.
     *
     * @return WorkerInterface The newly created worker.
     */
    public function create();
}
