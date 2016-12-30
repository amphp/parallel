<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Forking\Fork;
use Amp\Parallel\Sync\{ PosixSemaphore, Semaphore };

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
        \Amp\execute(function () {
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

    /**
     * @requires extension pcntl
     */
    public function testAcquireInMultipleForks() {
        \Amp\execute(function () {
            $this->semaphore = $this->createSemaphore(1);

            $fork1 = new Fork(function (Semaphore $semaphore) {
                $lock = yield $semaphore->acquire();

                usleep(100000);

                $lock->release();

                return 0;
            }, $this->semaphore);

            $fork2 = new Fork(function (Semaphore $semaphore) {
                $lock = yield $semaphore->acquire();

                usleep(100000);

                $lock->release();

                return 1;
            }, $this->semaphore);

            $start = microtime(true);

            $fork1->start();
            $fork2->start();

            yield $fork1->join();
            yield $fork2->join();

            $this->assertGreaterThan(0.1, microtime(true) - $start);
        });
    }
}
