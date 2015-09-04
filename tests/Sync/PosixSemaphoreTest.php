<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Forking\Fork;
use Icicle\Concurrent\Sync\PosixSemaphore;
use Icicle\Concurrent\Sync\SemaphoreInterface;
use Icicle\Coroutine;
use Icicle\Loop;

/**
 * @group posix
 * @requires extension sysvmsg
 */
class PosixSemaphoreTest extends AbstractSemaphoreTest
{
    public function createSemaphore($locks)
    {
        return new PosixSemaphore($locks);
    }

    public function tearDown()
    {
        if (!$this->semaphore->isFreed()) {
            $this->semaphore->free();
        }
    }

    public function testFree()
    {
        $this->semaphore = $this->createSemaphore(1);

        $this->assertFalse($this->semaphore->isFreed());

        $this->semaphore->free();

        $this->assertTrue($this->semaphore->isFreed());
    }

    /**
     * @requires extension pcntl
     */
    public function testAcquireInMultipleForks()
    {
        Coroutine\create(function () {
            $this->semaphore = $this->createSemaphore(1);

            $fork1 = new Fork(function (SemaphoreInterface $semaphore) {
                $lock = (yield $semaphore->acquire());

                usleep(1e5);

                $lock->release();

                yield 0;
            }, $this->semaphore);

            $fork2 = new Fork(function (SemaphoreInterface $semaphore) {
                $lock = (yield $semaphore->acquire());

                usleep(1e5);

                $lock->release();

                yield 1;
            }, $this->semaphore);

            $start = microtime(true);

            $fork1->start();
            $fork2->start();

            yield $fork1->join();
            yield $fork2->join();

            $this->assertGreaterThan(1, microtime(true) - $start);
        });

        Loop\run();
    }
}
