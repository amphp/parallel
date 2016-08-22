<?php declare(strict_types = 1);

namespace Amp\Concurrent\Test\Sync;

use Amp\Concurrent\Sync\FileMutex;
use Amp\Concurrent\Test\TestCase;

class FileMutexTest extends TestCase {
    public function testAcquire() {
        \Amp\execute(function () {
            $mutex = new FileMutex;
            $lock = yield $mutex->acquire();
            $lock->release();
            $this->assertTrue($lock->isReleased());
        });

    }

    public function testAcquireMultiple() {
        $this->assertRunTimeGreaterThan(function () {
            \Amp\execute(function () {
                $mutex = new FileMutex;

                $lock1 = yield $mutex->acquire();
                \Amp\delay(500, function () use ($lock1) {
                    $lock1->release();
                });

                $lock2 = yield $mutex->acquire();
                \Amp\delay(500, function () use ($lock2) {
                    $lock2->release();
                });

                $lock3 = yield $mutex->acquire();
                \Amp\delay(500, function () use ($lock3) {
                    $lock3->release();
                });
            });
        }, 1.5);
    }
}
