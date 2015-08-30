<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\MutexInterface;

/**
 * A thread-safe, asynchronous mutex using the pthreads locking mechanism.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class Mutex implements MutexInterface
{
    /**
     * @var \Icicle\Concurrent\Threading\Internal\Mutex
     */
    private $mutex;

    /**
     * Creates a new threaded mutex.
     */
    public function __construct()
    {
        $this->mutex = new Internal\Mutex();
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        return $this->mutex->acquire();
    }
}
