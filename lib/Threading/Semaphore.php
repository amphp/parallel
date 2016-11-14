<?php declare(strict_types = 1);

namespace Amp\Parallel\Threading;

use Amp\Parallel\Sync\Semaphore as SyncSemaphore;
use Interop\Async\Promise;

/**
 * An asynchronous semaphore based on pthreads' synchronization methods.
 *
 * This is an implementation of a thread-safe semaphore that has non-blocking
 * acquire methods. There is a small tradeoff for asynchronous semaphores; you
 * may not acquire a lock immediately when one is available and there may be a
 * small delay. However, the small delay will not block the thread.
 */
class Semaphore implements SyncSemaphore {
    /** @var Internal\Semaphore */
    private $semaphore;

    /** @var int */
    private $maxLocks;

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $locks The maximum number of locks that can be acquired from the semaphore.
     */
    public function __construct(int $locks) {
        $this->init($locks);
    }

    /**
     * Initializes the semaphore with a given number of locks.
     *
     * @param int $locks
     */
    private function init(int $locks) {
        if ($locks < 1) {
            throw new \Error("The number of locks should be a positive integer");
        }

        $this->semaphore = new Internal\Semaphore($locks);
        $this->maxLocks = $locks;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int {
        return $this->semaphore->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): int {
        return $this->maxLocks;
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(): Promise {
        return $this->semaphore->acquire();
    }

    /**
     * Clones the semaphore, creating a new instance with the same number of locks, all available.
     */
    public function __clone() {
        $this->init($this->getSize());
    }
}
