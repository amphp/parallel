<?php
namespace Icicle\Tests\Concurrent;

use Icicle\Concurrent\Sync\Internal\ExitSuccess;
use Icicle\Coroutine;
use Icicle\Loop;

abstract class AbstractContextTest extends TestCase
{
    abstract public function createContext(callable $function);

    public function testIsRunning()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                usleep(100);
            });

            $this->assertFalse($context->isRunning());

            $context->start();

            $this->assertTrue($context->isRunning());

            yield from $context->join();

            $this->assertFalse($context->isRunning());
        })->done();

        Loop\run();
    }

    public function testKill()
    {
        $context = $this->createContext(function () {
            usleep(1e6);
        });

        $context->start();

        $this->assertRunTimeLessThan([$context, 'kill'], 0.1);

        $this->assertFalse($context->isRunning());
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testStartWhileRunningThrowsError()
    {
        $context = $this->createContext(function () {
            usleep(100);
        });

        $context->start();
        $context->start();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testStartMultipleTimesThrowsError()
    {
        Loop\loop();

        $this->assertRunTimeGreaterThan(function () {
            Coroutine\create(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                yield from $context->join();

                $context->start();
                yield from $context->join();
            })->done();

            Loop\run();
        }, 2);
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\PanicError
     */
    public function testExceptionInContextPanics()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                throw new \Exception('Exception in fork.');
            });

            $context->start();
            yield from $context->join();
        })->done();

        Loop\run();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\PanicError
     */
    public function testReturnUnserializableDataPanics()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                return yield function () {};
            });

            $context->start();
            yield from $context->join();
        })->done();

        Loop\run();
    }

    public function testJoinWaitsForChild()
    {
        Loop\loop();

        $this->assertRunTimeGreaterThan(function () {
            Coroutine\create(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                yield from $context->join();
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
            $context = $this->createContext(function () {
                usleep(100);
            });

            yield from $context->join();
        })->done();

        Loop\run();
    }

    public function testJoinResolvesWithContextReturn()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                return 42;
            });

            $context->start();
            $this->assertSame(42, yield from $context->join());
        })->done();

        Loop\run();
    }

    public function testSendAndReceive()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                yield from $this->send(1);
                $value = yield from $this->receive();
                return $value;
            });

            $value = 42;

            $context->start();
            $this->assertSame(1, yield from $context->receive());
            yield $context->send($value);
            $this->assertSame($value, yield from $context->join());
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\SynchronizationError
     */
    public function testJoinWhenContextSendingData()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                yield from $this->send(0);
                return 42;
            });

            $context->start();
            $value = yield from $context->join();
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testReceiveBeforeContextHasStarted()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                yield from $this->send(0);
                return 42;
            });

            $value = yield from $context->receive();
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\StatusError
     */
    public function testSendBeforeContextHasStarted()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                yield from $this->send(0);
                return 42;
            });

            yield from $context->send(0);
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Concurrent\Exception\SynchronizationError
     */
    public function testReceiveWhenContextHasReturned()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                yield from $this->send(0);
                return 42;
            });

            $context->start();
            $value = yield from $context->receive();
            $value = yield from $context->receive();
            $value = yield from $context->join();
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testSendExitStatus()
    {
        Coroutine\create(function () {
            $context = $this->createContext(function () {
                $value = yield from $this->receive();
                return 42;
            });

            $context->start();
            yield from $context->send(new ExitSuccess(0));
            $value = yield from $context->join();
        })->done();

        Loop\run();
    }
}
