<?php

namespace Amp\Parallel\Threading\Internal;

use Amp\{ Coroutine, Pause };
use Amp\Parallel\Sync\Lock;
use AsyncInterop\Promise;

/**
 * @internal
 */
class Mutex extends \Threaded {
    const LATENCY_TIMEOUT =  10;

    /** @var bool */
    private $lock = true;
    
    /**
     * @return \AsyncInterop\Promise
     */
    public function acquire(): Promise {
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
