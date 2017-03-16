<?php

namespace Amp\Parallel\Test\Threading;

use Amp\Parallel\Threading\Thread;
use Amp\Parallel\Test\AbstractContextTest;
use Amp\Loop;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends AbstractContextTest {
    public function createContext(callable $function) {
        return new Thread($function);
    }

    public function testSpawnStartsThread() {
        Loop::run(function () {
            $thread = Thread::spawn(function () {
                usleep(100);
            });

            return yield $thread->join();
        });

    }
}
