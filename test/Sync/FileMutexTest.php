<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\FileMutex;
use Amp\Parallel\Test\TestCase;
use AsyncInterop\Loop;

class FileMutexTest extends TestCase {
    public function testAcquire() {
        Loop::execute(\Amp\wrap(function () {
            $mutex = new FileMutex;
            $lock = yield $mutex->acquire();
            $lock->release();
            $this->assertTrue($lock->isReleased());
        }));

    }

    public function testAcquireMultiple() {
        $this->assertRunTimeGreaterThan(function () {
            Loop::execute(\Amp\wrap(function () {
                $mutex = new FileMutex;

                $lock1 = yield $mutex->acquire();
                Loop::delay(500, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield $mutex->acquire();
                Loop::delay(500, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield $mutex->acquire();
                Loop::delay(500, function () use ($lock3) {
                    $lock3->release();
                });
            }));
        }, 1.5);
    }
}
