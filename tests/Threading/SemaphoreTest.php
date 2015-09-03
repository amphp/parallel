<?php
namespace Icicle\Tests\Concurrent\Threading;

use Icicle\Concurrent\Sync\Lock;
use Icicle\Concurrent\Threading\Semaphore;
use Icicle\Concurrent\Threading\Thread;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 * @requires extension pthreads
 */
class SemaphoreTest extends TestCase
{
    public function testCount()
    {
        $semaphore = new Semaphore(1);
        $this->assertEquals(1, $semaphore->count());
    }

    /**
     * @depends testCount
     */
    public function testInvalidLockCount()
    {
        $semaphore = new Semaphore(0);
        $this->assertEquals(1, $semaphore->count());
    }

    public function testAcquire()
    {
        Coroutine\create(function () {
            $semaphore = new Semaphore(1);
            $lock = (yield $semaphore->acquire());
            $lock->release();
            $this->assertTrue($lock->isReleased());
        });

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        $this->assertRunTimeGreaterThan(function () {
            Coroutine\create(function () {
                $semaphore = new Semaphore(1);

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
        }, 1.5);
    }

    public function testSimultaneousAcquire()
    {
        $semaphore = new Semaphore(1);

        $coroutine1 = new Coroutine\Coroutine($semaphore->acquire());
        $coroutine2 = new Coroutine\Coroutine($semaphore->acquire());

        $coroutine1->delay(0.5)->then(function (Lock $lock) {
            $lock->release();
        });

        $coroutine2->delay(0.5)->then(function (Lock $lock) {
            $lock->release();
        });

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', 1);
    }

    /**
     * @depends testAcquireMultiple
     */
    public function testAcquireInMultipleThreads()
    {
        Coroutine\create(function () {
            $semaphore = new Semaphore(1);

            $thread1 = new Thread(function (Semaphore $semaphore) {
                $lock = (yield $semaphore->acquire());

                usleep(1e5);

                $lock->release();

                yield 0;
            }, $semaphore);

            $thread2 = new Thread(function (Semaphore $semaphore) {
                $lock = (yield $semaphore->acquire());

                usleep(1e5);

                $lock->release();

                yield 1;
            }, $semaphore);

            $start = microtime(true);

            $thread1->start();
            $thread2->start();

            yield $thread1->join();
            yield $thread2->join();

            $this->assertGreaterThan(1, microtime(true) - $start);
        });

        Loop\run();
    }
}
