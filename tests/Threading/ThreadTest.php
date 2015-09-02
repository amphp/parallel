<?php
namespace Icicle\Tests\Concurrent\Threading;

use Icicle\Concurrent\Sync\Internal\ExitSuccess;
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
    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testConstructWithClosureWithStaticVariables()
    {
        $value = 1;

        $thread = new Thread(function () use ($value) {
            return $value;
        });
    }

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
            $this->assertSame(42, (yield $thread->join()));
        })->done();

        Loop\run();
    }

    public function testSendAndReceive()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                yield $this->send(1);
                $value = (yield $this->receive());
                yield $value;
            });

            $value = 42;

            $thread->start();
            $this->assertSame(1, (yield $thread->receive()));
            yield $thread->send($value);
            $this->assertSame($value, (yield $thread->join()));
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\SynchronizationError
     */
    public function testJoinWhenThreadSendingData()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                yield $this->send(0);
                yield 42;
            });

            $thread->start();
            $value = (yield $thread->join());
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testReceiveBeforeThreadHasStarted()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                yield $this->send(0);
                yield 42;
            });

            $value = (yield $thread->receive());
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testSendBeforeThreadHasStarted()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                yield $this->send(0);
                yield 42;
            });

            yield $thread->send(0);
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\SynchronizationError
     */
    public function testReceiveWhenThreadHasReturned()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                yield $this->send(0);
                yield 42;
            });

            $thread->start();
            $value = (yield $thread->receive());
            $value = (yield $thread->receive());
            $value = (yield $thread->join());
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testSendExitStatus()
    {
        Coroutine\create(function () {
            $thread = new Thread(function () {
                $value = (yield $this->receive());
                yield 42;
            });

            $thread->start();
            yield $thread->send(new ExitSuccess(0));
            $value = (yield $thread->join());
        })->done();

        Loop\run();
    }
}
