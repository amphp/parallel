<?php
namespace Icicle\Tests\Concurrent\Forking;

use Icicle\Concurrent\Forking\Fork;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\AbstractContextTest;

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
