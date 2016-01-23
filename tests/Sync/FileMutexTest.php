<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\FileMutex;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

class FileMutexTest extends TestCase
{
    public function testAcquire()
    {
        Coroutine\create(function () {
            $mutex = new FileMutex();
            $lock = yield from $mutex->acquire();
            $lock->release();
            $this->assertTrue($lock->isReleased());
        })->done();

        Loop\run();
    }

    public function testAcquireMultiple()
    {
        Loop\loop();

        $this->assertRunTimeGreaterThan(function () {
            Coroutine\create(function () {
                $mutex = new FileMutex();

                $lock1 = yield from $mutex->acquire();
                Loop\timer(0.5, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield from $mutex->acquire();
                Loop\timer(0.5, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield from $mutex->acquire();
                Loop\timer(0.5, function () use ($lock3) {
                    $lock3->release();
                });
            });

            Loop\run();
        }, 1.5);
    }
}
