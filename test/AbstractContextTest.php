<?php

namespace Amp\Parallel\Test;

use Amp\Parallel\Sync\Internal\ExitSuccess;

abstract class AbstractContextTest extends TestCase {
    /**
     * @param callable $function
     *
     * @return \Amp\Parallel\Context
     */
    abstract public function createContext(callable $function);

    public function testIsRunning() {
        \Amp\execute(function () {
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

        $this->assertRunTimeLessThan([$context, 'kill'], 0.1);

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
            \Amp\execute(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                yield $context->join();

                $context->start();
                yield $context->join();
            });
        }, 2);
    }

    /**
     * @expectedException \Amp\Parallel\PanicError
     */
    public function testExceptionInContextPanics() {
        \Amp\execute(function () {
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
        \Amp\execute(function () {
            $context = $this->createContext(function () {
                return yield function () {};
            });

            $context->start();
            yield $context->join();
        });
    }

    public function testJoinWaitsForChild() {
        $this->assertRunTimeGreaterThan(function () {
            \Amp\execute(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                yield $context->join();
            });

        }, 1);
    }

    /**
     * @expectedException \Amp\Parallel\StatusError
     */
    public function testJoinWithoutStartThrowsError() {
        \Amp\execute(function () {
            $context = $this->createContext(function () {
                usleep(100);
            });

            yield $context->join();
        });
    }

    public function testJoinResolvesWithContextReturn() {
        \Amp\execute(function () {
            $context = $this->createContext(function () {
                return 42;
            });

            $context->start();
            $this->assertSame(42, yield $context->join());
        });
    }

    public function testSendAndReceive() {
        \Amp\execute(function () {
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
        \Amp\execute(function () {
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
        \Amp\execute(function () {
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
        \Amp\execute(function () {
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
        \Amp\execute(function () {
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
    public function testSendExitStatus() {
        \Amp\execute(function () {
            $context = $this->createContext(function () {
                $value = yield $this->receive();
                return 42;
            });

            $context->start();
            yield $context->send(new ExitSuccess(0));
            $value = yield $context->join();
        });
    }
}
