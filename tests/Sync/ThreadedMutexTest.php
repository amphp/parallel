<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\ThreadedMutex;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 */
class ThreadedMutexTest extends TestCase
{
    public function testAcquire()
    {
        Coroutine\create(function () {
            $mutex = new ThreadedMutex();
            $lock = (yield $mutex->acquire());
            $lock->release();
            $this->assertTrue($lock->isReleased());
        })->done();

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        $this->assertRunTimeBetween(function () {
            Coroutine\create(function () {
                $mutex = new ThreadedMutex();

                $lock1 = (yield $mutex->acquire());
                Loop\timer(0.5, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = (yield $mutex->acquire());
                Loop\timer(0.5, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = (yield $mutex->acquire());
                Loop\timer(0.5, function () use ($lock3) {
                    $lock3->release();
                });
            });

            Loop\run();
        }, 1.5, 1.65);
    }
}
