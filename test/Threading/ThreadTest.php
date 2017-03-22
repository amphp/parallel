<?php

namespace Amp\Parallel\Test\Threading;

use Amp\Loop;
use Amp\Parallel\Threading\Thread;
use Amp\Parallel\Test\AbstractContextTest;

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
