<?php

namespace Amp\Tests\Concurrent\Threading;

use Amp\Concurrent\Threading\Thread;
use Amp\Coroutine;
use Amp\Loop;
use Amp\Tests\Concurrent\AbstractContextTest;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends AbstractContextTest
{
    public function createContext(callable $function)
    {
        return new Thread($function);
    }

    public function testSpawnStartsThread()
    {
        Coroutine\create(function () {
            $thread = Thread::spawn(function () {
                usleep(100);
            });

            return yield from $thread->join();
        })->done();

        Loop\run();
    }
}
