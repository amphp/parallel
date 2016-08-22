<?php declare(strict_types = 1);

namespace Amp\Concurrent\Threading;

use Amp\Concurrent\Sync\Parcel as SyncParcel;
use Amp\Coroutine;
use Interop\Async\Awaitable;

/**
 * A thread-safe container that shares a value between multiple threads.
 */
class Parcel implements SyncParcel {
    /**
     * @var \Amp\Concurrent\Threading\Mutex
     */
    private $mutex;

    /**
     * @var \Amp\Concurrent\Threading\Internal\Storage
     */
    private $storage;

    /**
     * Creates a new shared object container.
     *
     * @param mixed $value The value to store in the container.
     */
    public function __construct($value) {
        $this->init($value);
    }

    /**
     * @param mixed $value
     */
    private function init($value) {
        $this->mutex = new Mutex;
        $this->storage = new Internal\Storage($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap() {
        return $this->storage->get();
    }

    /**
     * {@inheritdoc}
     */
    protected function wrap($value) {
        $this->storage->set($value);
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    public function synchronized(callable $callback): Awaitable {
        return new Coroutine($this->doSynchronized($callback));
    }
    
    /**
     * @coroutine
     *
     * Asynchronously invokes a callable while maintaining an exclusive lock on the container.
     *
     * @param callable<mixed> $callback The function to invoke. The value in the container will be passed as the first
     *     argument.
     *
     * @return \Generator
     */
    private function doSynchronized(callable $callback): \Generator {
        /** @var \Amp\Concurrent\Sync\Lock $lock */
        $lock = yield $this->mutex->acquire();

        try {
            $value = $this->unwrap();
            $result = $callback($value);
            
            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }
            
            if ($result instanceof Awaitable) {
                yield $result;
            }
            
            $this->wrap(null === $result ? $value : $result);
        } finally {
            $lock->release();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function __clone() {
        $this->init($this->unwrap());
    }
}
