<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\SemaphoreException;

/**
 * A wrapper for a semaphore based on the "sync" extension.
 */
class ExtSyncSemaphore implements SemaphoreInterface
{
    /**
     * @var \SyncSemaphore A semaphore instance.
     */
    private $semaphore;

    /**
     * Creates a new semaphore object.
     *
     * @param int $maxLocks The maximum number of processes that can lock the semaphore.
     */
    public function __construct($maxLocks = 1)
    {
        $this->semaphore = new \SyncSemaphore(null, $maxLocks);
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        $this->semaphore->lock(-1);
    }

    /**
     * {@inheritdoc}
     */
    public function release()
    {
        $this->semaphore->unlock();
    }
}
