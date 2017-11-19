<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Loop;
use Amp\Parallel\Sync\PosixSemaphore;

/**
 * @group posix
 * @requires extension sysvmsg
 */
class PosixSemaphoreTest extends AbstractSemaphoreTest {
    /**
     * @param $locks
     *
     * @return \Amp\Parallel\Sync\PosixSemaphore
     */
    public function createSemaphore(int $locks) {
        return new PosixSemaphore($locks);
    }

    public function tearDown() {
        if ($this->semaphore && !$this->semaphore->isFreed()) {
            $this->semaphore->free();
        }
    }

    public function testCloneIsNewSemaphore() {
        Loop::run(function () {
            $this->semaphore = $this->createSemaphore(1);
            $clone = clone $this->semaphore;

            $lock = yield $clone->acquire();

            $this->assertCount(1, $this->semaphore);
            $this->assertCount(0, $clone);

            $lock->release();

            $clone->free();
        });
    }

    public function testFree() {
        $this->semaphore = $this->createSemaphore(1);

        $this->assertFalse($this->semaphore->isFreed());

        $this->semaphore->free();

        $this->assertTrue($this->semaphore->isFreed());
    }
}
