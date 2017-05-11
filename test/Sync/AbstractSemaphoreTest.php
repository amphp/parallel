<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

abstract class AbstractSemaphoreTest extends TestCase {
    /**
     * @var \Amp\Parallel\Sync\Semaphore
     */
    protected $semaphore;

    /**
     * @param int $locks Number of locks in the semaphore.
     *
     * @return \Amp\Parallel\Sync\Semaphore
     */
    abstract public function createSemaphore(int $locks);

    public function testCount() {
        $this->semaphore = $this->createSemaphore(4);

        $this->assertCount(4, $this->semaphore);
    }

    public function testAcquire() {
        Loop::run(function () {
            $this->semaphore = $this->createSemaphore(1);

            $lock = yield $this->semaphore->acquire();

            $this->assertFalse($lock->isReleased());

            $lock->release();

            $this->assertTrue($lock->isReleased());
        });
    }

    public function testAcquireMultiple() {
        $this->assertRunTimeGreaterThan(function () {
            $this->semaphore = $this->createSemaphore(1);

            Loop::run(function () {
                $lock1 = yield $this->semaphore->acquire();
                Loop::delay(500, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield $this->semaphore->acquire();
                Loop::delay(500, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield $this->semaphore->acquire();
                Loop::delay(500, function () use ($lock3) {
                    $lock3->release();
                });
            });
        }, 1500);
    }

    public function testCloneIsNewSemaphore() {
        Loop::run(function () {
            $this->semaphore = $this->createSemaphore(1);
            $clone = clone $this->semaphore;

            $lock = yield $clone->acquire();

            $this->assertCount(1, $this->semaphore);
            $this->assertCount(0, $clone);

            $lock->release();
        });
    }

    public function testSerializedIsSameSemaphore() {
        Loop::run(function () {
            $this->semaphore = $this->createSemaphore(1);
            $unserialized = unserialize(serialize($this->semaphore));

            $lock = yield $unserialized->acquire();

            $this->assertCount(0, $this->semaphore);
            $this->assertCount(0, $unserialized);

            $lock->release();
        });
    }

    public function testSimultaneousAcquire() {
        $this->semaphore = $this->createSemaphore(1);

        $callback = function () {
            $awaitable1 = $this->semaphore->acquire();
            $awaitable2 = $this->semaphore->acquire();
            
            yield new Delayed(500);
            
            (yield $awaitable1)->release();
            
            yield new Delayed(500);
            
            (yield $awaitable2)->release();
        };

        $this->assertRunTimeGreaterThan('Amp\Loop::run', 1000, [$callback]);
    }
}
