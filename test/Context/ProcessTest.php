<?php

namespace Amp\Parallel\Test\Context;

use Amp\Loop;
use Amp\Parallel\Context\Process;
use Amp\PHPUnit\TestCase;

class ProcessTest extends TestCase
{
    public function testBasicProcess()
    {
        Loop::run(function () {
            $process = new Process([
                __DIR__ . "/Fixtures/test-process.php",
                "Test"
            ]);
            yield $process->start();
            $this->assertSame("Test", yield $process->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No string provided
     */
    public function testFailingProcess()
    {
        Loop::run(function () {
            $process = new Process(__DIR__ . "/Fixtures/test-process.php");
            yield $process->start();
            yield $process->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No script found at '../test-process.php'
     */
    public function testInvalidScriptPath()
    {
        Loop::run(function () {
            $process = new Process("../test-process.php");
            yield $process->start();
            yield $process->join();
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage The given data cannot be sent because it is not serializable
     */
    public function testInvalidResult()
    {
        Loop::run(function () {
            $process = new Process(__DIR__ . "/Fixtures/invalid-result-process.php");
            yield $process->start();
            \var_dump(yield $process->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage did not return a callable function
     */
    public function testNoCallbackReturned()
    {
        Loop::run(function () {
            $process = new Process(__DIR__ . "/Fixtures/no-callback-process.php");
            yield $process->start();
            \var_dump(yield $process->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage contains a parse error
     */
    public function testParseError()
    {
        Loop::run(function () {
            $process = new Process(__DIR__ . "/Fixtures/parse-error-process.inc");
            yield $process->start();
            \var_dump(yield $process->join());
        });
    }

    /**
     * @expectedException \Amp\Parallel\Context\ContextException
     * @expectedExceptionMessage Failed to receive result from process
     */
    public function testKillWhenJoining()
    {
        Loop::run(function () {
            $process = new Process([
                __DIR__ . "/Fixtures/sleep-process.php",
                5,
            ]);
            yield $process->start();
            $promise = $process->join();
            $process->kill();
            $this->assertFalse($process->isRunning());
            yield $promise;
        });
    }
}
