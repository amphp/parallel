<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\ParcelInterface;

/**
 * A thread-safe container that shares a value between multiple threads.
 */
class Parcel implements ParcelInterface
{
    /**
     * @var \Icicle\Concurrent\Threading\Mutex
     */
    private $mutex;

    /**
     * @var \Icicle\Concurrent\Threading\Internal\Storage
     */
    private $storage;

    /**
     * Creates a new shared object container.
     *
     * @param mixed $value The value to store in the container.
     */
    public function __construct($value)
    {
        $this->init($value);
    }

    /**
     * @param mixed $value
     */
    private function init($value)
    {
        $this->mutex = new Mutex();
        $this->storage = new Internal\Storage($value);
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
    protected function wrap($value)
    {
        $this->storage->set($value);
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
    public function synchronized(callable $callback)
    {
        /** @var \Icicle\Concurrent\Sync\Lock $lock */
        $lock = (yield $this->mutex->acquire());

        try {
            $value = $this->unwrap();
            $result = (yield $callback($value));
            $this->wrap(null === $result ? $value : $result);
        } finally {
            $lock->release();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        $this->init($this->unwrap());
    }
}
