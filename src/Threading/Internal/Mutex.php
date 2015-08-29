<?php
namespace Icicle\Concurrent\Threading\Internal;

use Icicle\Concurrent\Sync\Lock;
use Icicle\Coroutine;

/**
 * @internal
 */
class Mutex extends \Threaded
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
