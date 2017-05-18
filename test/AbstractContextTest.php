<?php

namespace Amp\Parallel\Test;

use Amp\Loop;
use Amp\Parallel\Sync\Internal\ExitSuccess;
use Amp\PHPUnit\TestCase;

abstract class AbstractContextTest extends TestCase {
    /**
     * @param callable $function
     *
     * @return \Amp\Parallel\Context
     */
    abstract public function createContext(callable $function);

    public function testIsRunning() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                usleep(100);
            });

            $this->assertFalse($context->isRunning());

            $context->start();

            $this->assertTrue($context->isRunning());

            yield $context->join();

            $this->assertFalse($context->isRunning());
        });
    }

    public function testKill() {
        $context = $this->createContext(function () {
            usleep(1e6);
        });

        $context->start();

        $this->assertRunTimeLessThan([$context, 'kill'], 1000);

        $this->assertFalse($context->isRunning());
    }

    /**
     * @expectedException \Amp\Parallel\StatusError
     */
    public function testStartWhileRunningThrowsError() {
        $context = $this->createContext(function () {
            usleep(100);
        });

        $context->start();
        $context->start();
    }

    /**
     * @expectedException \Amp\Parallel\StatusError
     */
    public function testStartMultipleTimesThrowsError() {
        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                yield $context->join();

                $context->start();
                yield $context->join();
            });
        }, 2000);
    }

    /**
     * @expectedException \Amp\Parallel\PanicError
     */
    public function testExceptionInContextPanics() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                throw new \Exception('Exception in fork.');
            });

            $context->start();
            yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\PanicError
     */
    public function testReturnUnserializableDataPanics() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                return yield function () {};
            });

            $context->start();
            yield $context->join();
        });
    }

    public function testJoinWaitsForChild() {
        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                yield $context->join();
            });
        }, 1000);
    }

    /**
     * @expectedException \Amp\Parallel\StatusError
     */
    public function testJoinWithoutStartThrowsError() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                usleep(100);
            });

            yield $context->join();
        });
    }

    public function testJoinResolvesWithContextReturn() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                return 42;
            });

            $context->start();
            $this->assertSame(42, yield $context->join());
        });
    }

    public function testSendAndReceive() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield $this->send(1);
                $value = yield $this->receive();
                return $value;
            });

            $value = 42;

            $context->start();
            $this->assertSame(1, yield $context->receive());
            yield $context->send($value);
            $this->assertSame($value, yield $context->join());
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\SynchronizationError
     */
    public function testJoinWhenContextSendingData() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield $this->send(0);
                return 42;
            });

            $context->start();
            $value = yield $context->join();
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\StatusError
     */
    public function testReceiveBeforeContextHasStarted() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield $this->send(0);
                return 42;
            });

            $value = yield $context->receive();
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\StatusError
     */
    public function testSendBeforeContextHasStarted() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield $this->send(0);
                return 42;
            });

            yield $context->send(0);
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Amp\Parallel\SynchronizationError
     */
    public function testReceiveWhenContextHasReturned() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                yield $this->send(0);
                return 42;
            });

            $context->start();
            $value = yield $context->receive();
            $value = yield $context->receive();
            $value = yield $context->join();
        });
    }

    /**
     * @depends testSendAndReceive
     * @expectedException \Error
     */
    public function testSendExitResult() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                $value = yield $this->receive();
                return 42;
            });

            $context->start();
            yield $context->send(new ExitSuccess(0));
            $value = yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\ContextException
     * @expectedExceptionMessage The context stopped responding
     */
    public function testExitingContextOnJoin() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                exit;
            });

            $context->start();
            $value = yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\ContextException
     * @expectedExceptionMessage The context stopped responding
     */
    public function testExitingContextOnReceive() {
        Loop::run(function () {
            $context = $this->createContext(function () {
                exit;
            });

            $context->start();
            $value = yield $context->receive();
        });
    }
}
