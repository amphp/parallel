<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\ThreadedSemaphore;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadedSemaphoreTest extends TestCase
{
    public function testCount()
    {
        $semaphore = new ThreadedSemaphore(1);
        $this->assertEquals(1, $semaphore->count());
    }

    public function testAcquire()
    {
        Coroutine\create(function () {
            $semaphore = new ThreadedSemaphore(1);
            $lock = (yield $semaphore->acquire());
            $lock->release();
            $this->assertTrue($lock->isReleased());
        });

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        Loop\loop();

        $this->assertRunTimeBetween(function () {
            Coroutine\create(function () {
                $semaphore = new ThreadedSemaphore(1);

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
        }, 1.5, 1.65);
    }
}
