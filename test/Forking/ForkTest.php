<?php

namespace Amp\Tests\Concurrent\Forking;

use Amp\Concurrent\Forking\Fork;
use Amp\Coroutine;
use Amp\Loop;
use Amp\Tests\Concurrent\AbstractContextTest;

/**
 * @group forking
 * @requires extension pcntl
 */
class ForkTest extends AbstractContextTest
{
    public function createContext(callable $function)
    {
        return new Fork($function);
    }

    public function testSpawnStartsFork()
    {
        Coroutine\create(function () {
            $fork = Fork::spawn(function () {
                usleep(100);
            });

            return yield from $fork->join();
        })->done();

        Loop\run();
    }
}
