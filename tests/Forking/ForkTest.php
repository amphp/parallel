<?php
namespace Icicle\Tests\Concurrent\Forking;

use Icicle\Concurrent\Sync\Internal\ExitSuccess;
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

            yield $fork->join();
        })->done();

        Loop\run();
    }
}
