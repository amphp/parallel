<?php

namespace Amp\Parallel\Thread;

use Amp\Parallel\Sync\Mutex as SyncMutex;
use Amp\Promise;

/**
 * A thread-safe, asynchronous mutex using the pthreads locking mechanism.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class Mutex implements SyncMutex {
    /** @var \Amp\Parallel\Thread\Internal\Mutex */
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
        $this->mutex = new Internal\Mutex;
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(): Promise {
        return $this->mutex->acquire();
    }

    /**
     * Makes a copy of the mutex in the unlocked state.
     */
    public function __clone() {
        $this->init();
    }
}
