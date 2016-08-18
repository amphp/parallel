<?php

namespace Amp\Tests\Concurrent\Sync;

use Amp\Concurrent\Sync\FileMutex;
use Amp\Coroutine;
use Amp\Loop;
use Amp\Tests\Concurrent\TestCase;

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
