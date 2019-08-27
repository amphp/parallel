<?php

namespace Amp\Parallel\Test\Context;

use Amp\Delayed;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ExitSuccess;
use Amp\Parallel\Sync\PanicError;
use Amp\Parallel\Sync\SynchronizationError;
use Amp\PHPUnit\AsyncTestCase;

/**
 * @requires extension pthreads
 */
class ThreadTest extends AsyncTestCase
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
        $context = $this->createContext(function () {
            \usleep(100);
        });

        $this->assertFalse($context->isRunning());

        yield $context->start();

        $this->assertTrue($context->isRunning());

        yield $context->join();

        $this->assertFalse($context->isRunning());
    }

    public function testKill()
    {
        $this->setTimeout(1000);

        $context = $this->createContext(function () {
            \usleep(1e6);
        });

        yield $context->start();

        $context->kill();

        $this->assertFalse($context->isRunning());
    }

    public function testStartWhileRunningThrowsError()
    {
        $this->expectException(StatusError::class);

        $context = $this->createContext(function () {
            \usleep(100);
        });

        yield $context->start();
        yield $context->start();
    }

    public function testStartMultipleTimesThrowsError()
    {
        $this->expectException(StatusError::class);

        $this->setMinimumRuntime(2000);

        $context = $this->createContext(function () {
            \sleep(1);
        });

        yield $context->start();
        yield $context->join();

        yield $context->start();
        yield $context->join();
    }

    public function testExceptionInContextPanics()
    {
        $this->expectException(PanicError::class);

        $context = $this->createContext(function () {
            throw new \Exception('Exception in fork.');
        });

        yield $context->start();
        yield $context->join();
    }

    public function testReturnUnserializableDataPanics()
    {
        $this->expectException(PanicError::class);

        $context = $this->createContext(function () {
            return yield function () {};
        });

        yield $context->start();
        yield $context->join();
    }

    public function testJoinWaitsForChild()
    {
        $this->setMinimumRuntime(1000);

        $context = $this->createContext(function () {
            \sleep(1);
            return 1;
        });

        yield $context->start();
        $this->assertSame(1, yield $context->join());
    }

    public function testJoinWithoutStartThrowsError()
    {
        $this->expectException(StatusError::class);

        $context = $this->createContext(function () {
            \usleep(100);
        });

        yield $context->join();
    }

    public function testJoinResolvesWithContextReturn()
    {
        $context = $this->createContext(function () {
            return 42;
        });

        yield $context->start();
        $this->assertSame(42, yield $context->join());
    }

    public function testSendAndReceive()
    {
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
    }

    /**
     * @depends testSendAndReceive
     */
    public function testJoinWhenContextSendingData()
    {
        $this->expectException(SynchronizationError::class);

        $context = $this->createContext(function (Channel $channel) {
            yield $channel->send(0);
            return 42;
        });

        yield $context->start();
        $value = yield $context->join();
    }

    /**
     * @depends testSendAndReceive
     */
    public function testReceiveBeforeContextHasStarted()
    {
        $this->expectException(StatusError::class);

        $context = $this->createContext(function (Channel $channel) {
            yield $channel->send(0);
            return 42;
        });

        $value = yield $context->receive();
    }

    /**
     * @depends testSendAndReceive
     */
    public function testSendBeforeContextHasStarted()
    {
        $this->expectException(StatusError::class);

        $context = $this->createContext(function (Channel $channel) {
            yield $channel->send(0);
            return 42;
        });

        yield $context->send(0);
    }

    /**
     * @depends testSendAndReceive
     */
    public function testReceiveWhenContextHasReturned()
    {
        $this->expectException(SynchronizationError::class);

        $context = $this->createContext(function (Channel $channel) {
            yield $channel->send(0);
            return 42;
        });

        yield $context->start();
        $value = yield $context->receive();
        $value = yield $context->receive();
        $value = yield $context->join();
    }

    /**
     * @depends testSendAndReceive
     */
    public function testSendExitResult()
    {
        $this->expectException(\Error::class);

        $context = $this->createContext(function (Channel $channel) {
            $value = yield $channel->receive();
            return 42;
        });

        yield $context->start();
        yield $context->send(new ExitSuccess(0));
        $value = yield $context->join();
    }

    public function testExitingContextOnJoin()
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Failed to receive result');

        $context = $this->createContext(function () {
            exit;
        });

        yield $context->start();
        $value = yield $context->join();
    }

    public function testExitingContextOnReceive()
    {
        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('The channel closed unexpectedly');

        $context = $this->createContext(function () {
            exit;
        });

        yield $context->start();
        $value = yield $context->receive();
    }

    public function testExitingContextOnSend()
    {
        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('Sending on the channel failed');

        $context = $this->createContext(function () {
            yield new Delayed(1000);
            exit;
        });

        yield $context->start();
        yield $context->send(\str_pad("", 1024 * 1024, "-"));
    }

    public function testGetId()
    {
        $context = $this->createContext(function () {
            yield new Delayed(100);
        });

        yield $context->start();
        $this->assertIsInt($context->getId());
        yield $context->join();

        $context = $this->createContext(function () {
            yield new Delayed(100);
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The thread has not been started');

        $context->getId();
    }

    public function testRunStartsThread()
    {
        $thread = yield Thread::run(function () {
            \usleep(100);
        });

        $this->assertInstanceOf(Thread::class, $thread);
        $this->assertTrue($thread->isRunning());

        return yield $thread->join();
    }
}
