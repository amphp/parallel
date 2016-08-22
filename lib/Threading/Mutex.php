<?php declare(strict_types = 1);

namespace Amp\Concurrent\Threading;

use Amp\Concurrent\Sync\Mutex as SyncMutex;
use Interop\Async\Awaitable;

/**
 * A thread-safe, asynchronous mutex using the pthreads locking mechanism.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class Mutex implements SyncMutex {
    /**
     * @var \Amp\Concurrent\Threading\Internal\Mutex
     */
    private $mutex;

    /**
     * Creates a new threaded mutex.
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initializes the mutex.
     */
    private function init() {
        $this->mutex = new Internal\Mutex();
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(): Awaitable {
        return $this->mutex->acquire();
    }

    /**
     * Makes a copy of the mutex in the unlocked state.
     */
    public function __clone() {
        $this->init();
    }
}
