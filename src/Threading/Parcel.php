<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\ParcelInterface;

/**
 * A thread-safe container that shares a value between multiple threads.
 */
class Parcel implements ParcelInterface
{
    private $mutex;
    private $storage;

    /**
     * Creates a new shared object container.
     *
     * @param mixed $value The value to store in the container.
     */
    public function __construct($value)
    {
        $this->mutex = new Mutex();
        $this->storage = new Internal\Storage();
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap()
    {
        return $this->storage->get();
    }

    /**
     * {@inheritdoc}
     */
    public function wrap($value)
    {
        $this->storage->set($value);
    }

    /**
     * @coroutine
     *
     * Asynchronously invokes a callable while maintaining an exclusive lock on
     * the container.
     *
     * @param callable<mixed> $function The function to invoke. The value in the
     *                                  container will be passed as the first
     *                                  argument.
     *
     * @return \Generator
     */
    public function synchronized(callable $function)
    {
        /** @var \Icicle\Concurrent\Sync\Lock $lock */
        $lock = (yield $this->mutex->acquire());

        try {
            $value = (yield $function($this->storage->get()));
            $this->storage->set($value);
        } finally {
            $lock->release();
        }

        yield $value;
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        $this->storage = clone $this->storage;
        $this->mutex = clone $this->mutex;
    }
}
