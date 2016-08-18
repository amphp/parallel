<?php

namespace Amp\Concurrent\Test\Threading;

use Amp\Concurrent\Threading\Thread;
use Amp\Concurrent\Test\AbstractContextTest;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends AbstractContextTest {
    public function createContext(callable $function) {
        return new Thread($function);
    }

    public function testSpawnStartsThread() {
        \Amp\execute(function () {
            $thread = Thread::spawn(function () {
                usleep(100);
            });

            return yield $thread->join();
        });

    }
}
