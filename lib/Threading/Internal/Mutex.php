<?php declare(strict_types = 1);

namespace Amp\Concurrent\Threading\Internal;

use Amp\Concurrent\Sync\Lock;
use Amp\Coroutine;
use Amp\Pause;
use Interop\Async\Awaitable;

/**
 * @internal
 */
class Mutex extends \Threaded {
    const LATENCY_TIMEOUT =  10;

    /**
     * @var bool
     */
    private $lock = true;
    
    /**
     * @return \Interop\Async\Awaitable
     */
    public function acquire(): Awaitable {
        return new Coroutine($this->doAcquire());
    }
    
    /**
     * Attempts to acquire the lock and sleeps for a time if the lock could not be acquired.
     *
     * @return \Generator
     */
    public function doAcquire(): \Generator {
        $tsl = function () {
            return ($this->lock ? $this->lock = false : true);
        };

        while (!$this->lock || $this->synchronized($tsl)) {
            yield new Pause(self::LATENCY_TIMEOUT);
        }

        return new Lock(function () {
            $this->release();
        });
    }

    /**
     * Releases the lock.
     */
    protected function release() {
        $this->lock = true;
    }
}
