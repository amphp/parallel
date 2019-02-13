<?php

namespace Amp\Parallel\Test\Context;

use Amp\Loop;
use Amp\Parallel\Context\Parallel;
use Amp\PHPUnit\TestCase;

class ParallelTest extends TestCase
{
    public function testBasicProcess()
    {
        Loop::run(function () {
            $thread = new Parallel(__DIR__ . "/test-parallel.php", "Test");
            yield $thread->start();
            $this->assertSame("Test", yield $thread->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No string provided
     */
    public function testFailingProcess()
    {
        Loop::run(function () {
            $thread = new Parallel(__DIR__ . "/test-process.php");
            yield $thread->start();
            yield $thread->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No script found at '../test-process.php'
     */
    public function testInvalidScriptPath()
    {
        Loop::run(function () {
            $thread = new Parallel("../test-process.php");
            yield $thread->start();
            yield $thread->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage The given data cannot be sent because it is not serializable
     */
    public function testInvalidResult()
    {
        Loop::run(function () {
            $thread = new Parallel(__DIR__ . "/invalid-result-process.php");
            yield $thread->start();
            \var_dump(yield $thread->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage did not return a callable function
     */
    public function testNoCallbackReturned()
    {
        Loop::run(function () {
            $thread = new Parallel(__DIR__ . "/no-callback-process.php");
            yield $thread->start();
            \var_dump(yield $thread->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage Uncaught ParseError in execution context
     */
    public function testParseError()
    {
        Loop::run(function () {
            $thread = new Parallel(__DIR__ . "/parse-error-process.inc");
            yield $thread->start();
            \var_dump(yield $thread->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\ContextException
     * @expectedExceptionMessage The context stopped responding, potentially due to a fatal error or calling exit
     */
    public function testKillWhenJoining()
    {
        Loop::run(function () {
            $thread = new Parallel(__DIR__ . "/sleep-process.php");
            yield $thread->start();
            $promise = $thread->join();
            $thread->kill();
            $this->assertFalse($thread->isRunning());
            yield $promise;
        });
    }
}
