<?php

namespace Amp\Parallel\Test\Context;

use Amp\Delayed;
use Amp\Loop;
use Amp\Parallel\Context\Context;
use Amp\PHPUnit\TestCase;

abstract class AbstractContextTest extends TestCase
{
    abstract public function createContext($script): Context;

    public function testBasicProcess()
    {
        Loop::run(function () {
            $context = $this->createContext([
                __DIR__ . "/Fixtures/test-process.php",
                "Test"
            ]);
            yield $context->start();
            $this->assertSame("Test", yield $context->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No string provided
     */
    public function testFailingProcess()
    {
        Loop::run(function () {
            $context = $this->createContext(__DIR__ . "/Fixtures/test-process.php");
            yield $context->start();
            yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No script found at '../test-process.php'
     */
    public function testInvalidScriptPath()
    {
        Loop::run(function () {
            $context = $this->createContext("../test-process.php");
            yield $context->start();
            yield $context->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage The given data cannot be sent because it is not serializable
     */
    public function testInvalidResult()
    {
        Loop::run(function () {
            $context = $this->createContext(__DIR__ . "/Fixtures/invalid-result-process.php");
            yield $context->start();
            \var_dump(yield $context->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage did not return a callable function
     */
    public function testNoCallbackReturned()
    {
        Loop::run(function () {
            $context = $this->createContext(__DIR__ . "/Fixtures/no-callback-process.php");
            yield $context->start();
            \var_dump(yield $context->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage contains a parse error
     */
    public function testParseError()
    {
        Loop::run(function () {
            $context = $this->createContext(__DIR__ . "/Fixtures/parse-error-process.inc");
            yield $context->start();
            \var_dump(yield $context->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\ContextException
     * @expectedExceptionMessage Failed to receive result
     */
    public function testKillWhenJoining()
    {
        Loop::run(function () {
            $context = $this->createContext([
                __DIR__ . "/Fixtures/delayed-process.php",
                5,
            ]);
            yield $context->start();
            yield new Delayed(100);
            $promise = $context->join();
            $context->kill();
            $this->assertFalse($context->isRunning());
            yield $promise;
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\ContextException
     * @expectedExceptionMessage Failed to receive result
     */
    public function testKillBusyContext()
    {
        Loop::run(function () {
            $context = $this->createContext([
                __DIR__ . "/Fixtures/sleep-process.php",
                5,
            ]);
            yield $context->start();
            yield new Delayed(100);
            $promise = $context->join();
            $context->kill();
            $this->assertFalse($context->isRunning());
            yield $promise;
        });
    }
}
