<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\PosixSemaphore;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group posix
 * @requires extension sysvmsg
 */
class PosixSemaphoreTest extends TestCase
{
    public function testFree()
    {
        $semaphore = new PosixSemaphore(1);

        $this->assertFalse($semaphore->isFreed());

        $semaphore->free();

        $this->assertTrue($semaphore->isFreed());
    }

    public function testCount()
    {
        $semaphore = new PosixSemaphore(4);

        $this->assertCount(4, $semaphore);

        $semaphore->free();
    }

    public function testAcquire()
    {
        Coroutine\create(function () {
            $semaphore = new PosixSemaphore(1);

            $lock = (yield $semaphore->acquire());
            $lock->release();

            $this->assertTrue($lock->isReleased());

            $semaphore->free();
        });

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        $this->assertRunTimeGreaterThan(function () {
            $semaphore = new PosixSemaphore(1);

            Coroutine\create(function () use ($semaphore) {
                $lock1 = (yield $semaphore->acquire());
                Loop\timer(0.5, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = (yield $semaphore->acquire());
                Loop\timer(0.5, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = (yield $semaphore->acquire());
                Loop\timer(0.5, function () use ($lock3) {
                    $lock3->release();
                });
            });

            Loop\run();
            $semaphore->free();
        }, 1.5);
    }

    public function tesCloneIsSameSemaphore()
    {
        Coroutine\create(function () {
            $semaphore = new PosixSemaphore(1);
            $clone = clone $semaphore;

            $lock = (yield $clone->acquire());

            $this->assertCount(0, $semaphore);
            $this->assertCount(0, $clone);

            $lock->release();
            $semaphore->free();
        });

        Loop\run();
    }

    public function testSerializedIsSameSemaphore()
    {
        Coroutine\create(function () {
            $semaphore = new PosixSemaphore(1);
            $unserialized = unserialize(serialize($semaphore));

            $lock = (yield $unserialized->acquire());

            $this->assertCount(0, $semaphore);
            $this->assertCount(0, $unserialized);

            $lock->release();
            $semaphore->free();
        });

        Loop\run();
    }
}
