<?php

namespace Amp\Parallel\Test\Context;

use Amp\Delayed;
use Amp\Loop;
use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ExitSuccess;
use Amp\PHPUnit\TestCase;

/**
 * @requires extension pthreads
 */
class ThreadTest extends TestCase
{
    /**
     * @param callable $function
     *
     * @return \Amp\Parallel\Context\Context
     */
    public function createContext(callable $function)
    {
        return new Thread($function);
    }

    public function testIsRunning()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                \usleep(100);
            });

            $this->assertFalse($context->isRunning());

            yield $context->start();

            $this->assertTrue($context->isRunning());

            yield $context->join();

            $this->assertFalse($context->isRunning());
        });
    }

    public function testKill()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                \usleep(1e6);
            });

            yield $context->start();

            $this->assertRunTimeLessThan([$context, 'kill'], 1000);

            $this->assertFalse($context->isRunning());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\StatusError
     */
    public function testStartWhileRunningThrowsError()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                \usleep(100);
            });

            yield $context->start();
            yield $context->start();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\StatusError
     */
    public function testStartMultipleTimesThrowsError()
    {
        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $context = $this->createContext(function () {
                    \sleep(1);
                });

                yield $context->start();
                yield $context->join();

                yield $context->start();
                yield $context->join();
            });
        }, 2000);
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     */
    public function testExceptionInContextPanics()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                throw new \Exception('Exception in fork.');
            });

            yield $context->start();
            yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     */
    public function testReturnUnserializableDataPanics()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                return yield function () {};
            });

            yield $context->start();
            yield $context->join();
        });
    }

    public function testJoinWaitsForChild()
    {
        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $context = $this->createContext(function () {
                    \sleep(1);
                });

                yield $context->start();
                yield $context->join();
            });
        }, 1000);
    }

    /**
     * @expectedException \Amp\Parallel\Context\StatusError
     */
    public function testJoinWithoutStartThrowsError()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                \usleep(100);
            });

            yield $context->join();
        });
    }

    public function testJoinResolvesWithContextReturn()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                return 42;
            });

            yield $context->start();
            $this->assertSame(42, yield $context->join());
        });
    }

    public function testSendAndReceive()
    {
        Loop::run(function () {
            $context = $this->createContext(function (Channel $channel) {
                yield $channel->send(1);
                $value = yield $channel->receive();
                return $value;
            });

            $value = 42;

            yield $context->start();
            $this->assertSame(1, yield $context->receive());
            yield $context->send($value);
            $this->assertSame($value, yield $context->join());
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\Sync\SynchronizationError
     */
    public function testJoinWhenContextSendingData()
    {
        Loop::run(function () {
            $context = $this->createContext(function (Channel $channel) {
                yield $channel->send(0);
                return 42;
            });

            yield $context->start();
            $value = yield $context->join();
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\Context\StatusError
     */
    public function testReceiveBeforeContextHasStarted()
    {
        Loop::run(function () {
            $context = $this->createContext(function (Channel $channel) {
                yield $channel->send(0);
                return 42;
            });

            $value = yield $context->receive();
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\Context\StatusError
     */
    public function testSendBeforeContextHasStarted()
    {
        Loop::run(function () {
            $context = $this->createContext(function (Channel $channel) {
                yield $channel->send(0);
                return 42;
            });

            yield $context->send(0);
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\Sync\SynchronizationError
     */
    public function testReceiveWhenContextHasReturned()
    {
        Loop::run(function () {
            $context = $this->createContext(function (Channel $channel) {
                yield $channel->send(0);
                return 42;
            });

            yield $context->start();
            $value = yield $context->receive();
            $value = yield $context->receive();
            $value = yield $context->join();
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Error
     */
    public function testSendExitResult()
    {
        Loop::run(function () {
            $context = $this->createContext(function (Channel $channel) {
                $value = yield $channel->receive();
                return 42;
            });

            yield $context->start();
            yield $context->send(new ExitSuccess(0));
            $value = yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\ContextException
     * @expectedExceptionMessage Failed to receive result
     */
    public function testExitingContextOnJoin()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                exit;
            });

            yield $context->start();
            $value = yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\ChannelException
     * @expectedExceptionMessage The channel closed unexpectedly
     */
    public function testExitingContextOnReceive()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                exit;
            });

            yield $context->start();
            $value = yield $context->receive();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\ChannelException
     * @expectedExceptionMessage Sending on the channel failed
     */
    public function testExitingContextOnSend()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield new Delayed(1000);
                exit;
            });

            yield $context->start();
            yield $context->send(\str_pad("", 1024 * 1024, "-"));
        });
    }

    public function testGetId()
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield new Delayed(100);
            });

            yield $context->start();
            $this->assertInternalType('int', $context->getId());
            yield $context->join();

            $context = $this->createContext(function () {
                yield new Delayed(100);
            });

            $this->expectException(\Error::class);
            $this->expectExceptionMessage('The thread has not been started');

            $context->getId();
        });
    }

    public function testRunStartsThread()
    {
        Loop::run(function () {
            $thread = yield Thread::run(function () {
                \usleep(100);
            });

            $this->assertInstanceOf(Thread::class, $thread);
            $this->assertTrue($thread->isRunning());

            return yield $thread->join();
        });
    }
}
