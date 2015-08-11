<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\PosixSemaphore;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group posix
 */
class PosixSemaphoreTest extends TestCase
{
    public function testAcquire()
    {
        Coroutine\create(function () {
            $semaphore = new PosixSemaphore(1);
            $lock = (yield $semaphore->acquire());
            $lock->release();
            $this->assertTrue($lock->isReleased());

            $semaphore->destroy();
        });

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        $this->assertRunTimeBetween(function () {
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
            $semaphore->destroy();
        }, 1.5, 1.65);
    }
}
