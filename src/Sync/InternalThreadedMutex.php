<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Coroutine;

/**
 * @internal
 */
class InternalThreadedMutex extends \Threaded
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var bool
     */
    private $lock = true;

    /**
     * Attempts to acquire the lock and sleeps for a time if the lock could not be acquired.
     *
     * @return \Generator
     */
    public function acquire()
    {
        $tsl = function () {
            return ($this->lock ? $this->lock = false : true);
        };

        while ($this->synchronized($tsl)) {
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
        }

        yield new Lock(function () {
            $this->release();
        });
    }

    /**
     * Releases the lock.
     */
    protected function release()
    {
        $this->lock = true;
    }
}