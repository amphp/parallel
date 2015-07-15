<?php
namespace Icicle\Concurrent\Forking;

/**
 * A synchronized object that safely shares its state across processes and
 * provides methods for process synchronization.
 *
 * When used with forking, the object must be created prior to forking for both
 * processes to access the synchronized object.
 */
abstract class Synchronized extends SharedObject
{
    /**
     * @var AsyncSemaphore A semaphore used for locking the object data.
     */
    private $semaphore;

    /**
     * Creates a new synchronized object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->semaphore = new AsyncSemaphore(1);
    }

    /**
     * Locks the object for read or write for the calling context.
     */
    public function lock()
    {
        return $this->semaphore->acquire();
    }

    /**
     * Unlocks the object.
     */
    public function unlock()
    {
        $this->__writeSynchronizedProperties();
        return $this->semaphore->release();
    }

    /**
     * Invokes a function while maintaining a lock for the calling context.
     *
     * @param callable $callback The function to invoke.
     *
     * @return mixed The value returned by the callback.
     */
    public function synchronized(callable $callback)
    {
        return $this->lock()->then(function () use ($callback) {
            try {
                $returnValue = $callback($this);
            } finally {
                $this->unlock();
            }

            return $returnValue;
        });
    }

    /**
     * Destroys the synchronized object safely on destruction.
     */
    public function __destruct()
    {
        /*$this->synchronized(function () {
            $this->destroy();
        });*/
    }
}
