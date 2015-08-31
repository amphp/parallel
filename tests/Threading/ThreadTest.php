<?php
namespace Icicle\Tests\Concurrent\Threading;

use Icicle\Concurrent\Threading\Thread;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends TestCase
{
    public function testIsRunning()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                usleep(100);
            });

            $this->assertFalse($thread->isRunning());

            $thread->start();

            $this->assertTrue($thread->isRunning());

            yield $thread->join();

            $this->assertFalse($thread->isRunning());
        })->done();

        Loop\run();
    }

    public function testKill()
    {
        $thread = new Thread(function () {
            usleep(1e6);
        });

        $thread->start();

        $this->assertRunTimeLessThan([$thread, 'kill'], 0.1);

        $this->assertFalse($thread->isRunning());
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testStartWhileRunningThrowsError()
    {
        $thread = new Thread(function () {
            usleep(100);
        });

        $thread->start();
        $thread->start();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testStartMultipleTimesThrowsError()
    {
        Loop\loop();

        $this->assertRunTimeGreaterThan(function () {
            Coroutine\create(function () {
                $thread = new Thread(function () {
                    sleep(1);
                });

                $thread->start();
                yield $thread->join();

                $thread->start();
                yield $thread->join();
            })->done();

            Loop\run();
        }, 2);
    }

    public function testSpawnStartsThread()
    {
        Coroutine\create(function () {
            $thread = Thread::spawn(function () {
                usleep(100);
            });

            yield $thread->join();
        })->done();

        Loop\run();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\PanicError
     */
    public function testExceptionInThreadPanics()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                throw new \Exception('Exception in thread.');
            });

            $thread->start();
            yield $thread->join();
        })->done();

        Loop\run();
    }

    public function testJoinWaitsForChild()
    {
        Loop\loop();

        $this->assertRunTimeGreaterThan(function () {
            Coroutine\create(function () {
                $thread = new Thread(function () {
                    sleep(1);
                });

                $thread->start();
                yield $thread->join();
            })->done();

            Loop\run();
        }, 1);
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testJoinWithoutStartThrowsError()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                usleep(100);
            });

            yield $thread->join();
        })->done();

        Loop\run();
    }

    public function testJoinResolvesWithThreadReturn()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                return 42;
            });

            $thread->start();
            $this->assertEquals(42, (yield $thread->join()));
        })->done();

        Loop\run();
    }
}
