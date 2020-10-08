<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Sync\ContextPanicError;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

abstract class AbstractContextTest extends AsyncTestCase
{
    abstract public function createContext(string|array $script): Context;

    public function testBasicProcess()
    {
        $context = $this->createContext([
                __DIR__ . "/Fixtures/test-process.php",
                "Test"
            ]);
        $context->start();
        $this->assertSame("Test", $context->join());
    }

    public function testFailingProcess()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('No string provided');

        $context = $this->createContext(__DIR__ . "/Fixtures/test-process.php");
        $context->start();
        $context->join();
    }

    public function testThrowingProcessOnReceive()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('Test message');

        $context = $this->createContext(__DIR__ . "/Fixtures/throwing-process.php");
        $context->start();
        $context->receive();
    }

    public function testThrowingProcessOnSend()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('Test message');

        $context = $this->createContext(__DIR__ . "/Fixtures/throwing-process.php");
        $context->start();
        delay(100);
        $context->send(1);
    }

    public function testInvalidScriptPath()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage("No script found at '../test-process.php'");

        $context = $this->createContext("../test-process.php");
        $context->start();
        $context->join();
    }

    public function testInvalidResult()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('The given data could not be serialized');

        $context = $this->createContext(__DIR__ . "/Fixtures/invalid-result-process.php");
        $context->start();
        \var_dump($context->join());
    }

    public function testNoCallbackReturned()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('did not return a callable function');

        $context = $this->createContext(__DIR__ . "/Fixtures/no-callback-process.php");
        $context->start();
        \var_dump($context->join());
    }

    public function testParseError()
    {
        $this->expectException(ContextPanicError::class);
        $this->expectExceptionMessage('contains a parse error');

        $context = $this->createContext(__DIR__ . "/Fixtures/parse-error-process.inc");
        $context->start();
        $context->join();
    }

    public function testKillWhenJoining()
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Failed to receive result');

        $context = $this->createContext([
                __DIR__ . "/Fixtures/delayed-process.php",
                5,
            ]);
        $context->start();
        delay(100);
        $promise = async(fn() => $context->join());
        $context->kill();
        $this->assertFalse($context->isRunning());
        await($promise);
    }

    public function testKillBusyContext()
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Failed to receive result');

        $context = $this->createContext([
                __DIR__ . "/Fixtures/sleep-process.php",
                5,
            ]);
        $context->start();
        delay(100);
        $promise = async(fn() => $context->join());
        $context->kill();
        $this->assertFalse($context->isRunning());
        await($promise);
    }

    public function testExitingProcess()
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Failed to receive result');

        $context = $this->createContext([
                __DIR__ . "/Fixtures/exiting-process.php",
                5,
            ]);
        $context->start();
        $context->join();
    }

    public function testExitingProcessOnReceive()
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('stopped responding');

        $context = $this->createContext(__DIR__ . "/Fixtures/exiting-process.php");
        $context->start();
        $context->receive();
    }

    public function testExitingProcessOnSend()
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('stopped responding');

        $context = $this->createContext(__DIR__ . "/Fixtures/exiting-process.php");
        $context->start();
        delay(500);
        $context->send(1);
    }
}
