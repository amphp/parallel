<?php

namespace Amp\Tests\Concurrent\Sync;

use Amp\Concurrent\Sync\Lock;
use Amp\Coroutine;
use Amp\Loop;
use Amp\Tests\Concurrent\TestCase;

abstract class AbstractSemaphoreTest extends TestCase
{
    /**
     * @var \Amp\Concurrent\Sync\Semaphore
     */
    protected $semaphore;

    /**
     * @return \Amp\Concurrent\Sync\Semaphore
     */
    abstract public function createSemaphore($locks);

    public function testCount()
    {
        $this->semaphore = $this->createSemaphore(4);

        $this->assertCount(4, $this->semaphore);
    }

    public function testAcquire()
    {
        Coroutine\create(function () {
            $this->semaphore = $this->createSemaphore(1);

            $lock = yield from $this->semaphore->acquire();

            $this->assertFalse($lock->isReleased());

            $lock->release();

            $this->assertTrue($lock->isReleased());
        })->done();

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        $this->assertRunTimeGreaterThan(function () {
            $this->semaphore = $this->createSemaphore(1);

            Coroutine\create(function () {
                $lock1 = yield from $this->semaphore->acquire();
                Loop\timer(0.5, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield from $this->semaphore->acquire();
                Loop\timer(0.5, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield from $this->semaphore->acquire();
                Loop\timer(0.5, function () use ($lock3) {
                    $lock3->release();
                });
            })->done();

            Loop\run();
        }, 1.5);
    }

    public function testCloneIsNewSemaphore()
    {
        Coroutine\create(function () {
            $this->semaphore = $this->createSemaphore(1);
            $clone = clone $this->semaphore;

            $lock = yield from $clone->acquire();

            $this->assertCount(1, $this->semaphore);
            $this->assertCount(0, $clone);

            $lock->release();
        })->done();

        Loop\run();
    }

    public function testSerializedIsSameSemaphore()
    {
        Coroutine\create(function () {
            $this->semaphore = $this->createSemaphore(1);
            $unserialized = unserialize(serialize($this->semaphore));

            $lock = yield from $unserialized->acquire();

            $this->assertCount(0, $this->semaphore);
            $this->assertCount(0, $unserialized);

            $lock->release();
        })->done();

        Loop\run();
    }

    public function testSimultaneousAcquire()
    {
        $this->semaphore = $this->createSemaphore(1);

        $coroutine1 = new Coroutine\Coroutine($this->semaphore->acquire());
        $coroutine2 = new Coroutine\Coroutine($this->semaphore->acquire());

        $coroutine1->delay(0.5)->then(function (Lock $lock) {
            $lock->release();
        });

        $coroutine2->delay(0.5)->then(function (Lock $lock) {
            $lock->release();
        });

        $this->assertRunTimeGreaterThan('Amp\Loop\run', 1);
    }
}
