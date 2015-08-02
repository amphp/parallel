<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\MutexException;

/**
 * A wrapper for a mutex based on the "sync" extension.
 */
class ExtSyncMutex implements MutexInterface
{
    /**
     * @var \SyncMutex A mutex instance.
     */
    private $mutex;

    /**
     * Creates a new mutex object.
     */
    public function __construct()
    {
        $this->mutex = new \SyncMutex();
    }

    /**
     * {@inheritdoc}
     */
    public function lock()
    {
        $this->mutex->lock(-1);
    }

    /**
     * {@inheritdoc}
     */
    public function unlock()
    {
        $this->mutex->unlock(false);
    }
}
