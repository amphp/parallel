<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\CancelledException;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use function Amp\async;
use function Amp\delay;

abstract class AbstractContextTest extends AsyncTestCase
{
    abstract public function createContext(string|array $script): Context;

    public function testBasicProcess(): void
    {
        $context = $this->createContext([
            __DIR__ . "/Fixtures/test-process.php",
            "Test"
        ]);
        self::assertSame("Test", $context->join());
    }

    public function testFailingProcess(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('No string provided');

        $context = $this->createContext(__DIR__ . "/Fixtures/test-process.php");
        $context->join();
    }

    public function testThrowingProcessOnReceive(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Test message');

        $context = $this->createContext(__DIR__ . "/Fixtures/throwing-process.php");

        $cancellation = new TimeoutCancellation(0.1);

        $context->receive($cancellation);

        self::fail('Receiving should have failed');
    }

    public function testThrowingProcessOnSend(): void
    {
        $this->expectException(ContextException::class);

        $context = $this->createContext(__DIR__ . "/Fixtures/throwing-process.php");
        delay(1); // Give process time to start.

        $context->send(1);

        delay(1); // await TCP RST

        $context->send(1);
        self::fail('Sending should have failed');
    }

    public function testInvalidScriptPath(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage("No script found at '../test-process.php'");

        $context = $this->createContext("../test-process.php");
        $context->join();
    }

    public function testInvalidResult(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('The given data could not be serialized');

        $context = $this->createContext(__DIR__ . "/Fixtures/invalid-result-process.php");
        $context->join();
    }

    public function testNoCallbackReturned(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('did not return a callable function');

        $context = $this->createContext(__DIR__ . "/Fixtures/no-callback-process.php");
        $context->join();
    }

    public function testParseError(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('contains a parse error');

        $context = $this->createContext(__DIR__ . "/Fixtures/parse-error-process.inc");
        $context->join();
    }

    public function testCloseWhenJoining(): void
    {
        $this->setTimeout(3);

        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('The context has already closed');

        $context = $this->createContext([
            __DIR__ . "/Fixtures/delayed-process.php",
            5,
        ]);
        $future = async($context->join(...));
        $context->close();
        $future->await();
    }

    public function testCloseBusyContext(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Failed to receive result');

        $context = $this->createContext([__DIR__ . "/Fixtures/sleep-process.php"]);
        $future = async($context->join(...));
        async($context->close(...));
        $future->await();
    }

    public function testExitingProcess(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Failed to receive result');

        $context = $this->createContext([__DIR__ . "/Fixtures/exiting-process.php"]);
        $context->join();
    }

    public function testExitingProcessOnReceive(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('stopped responding');

        $context = $this->createContext(__DIR__ . "/Fixtures/exiting-process.php");
        $context->receive();
    }

    public function testExitingProcessOnSend(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('stopped responding');

        $context = $this->createContext(__DIR__ . "/Fixtures/exiting-process.php");
        delay(1);
        $context->send(1);
        delay(1); // Await TCP RST
        $context->send(1);
    }

    public function testCancelJoin(): void
    {
        $this->setTimeout(2);

        $context = $this->createContext([
            __DIR__ . "/Fixtures/delayed-process.php",
            1,
        ]);

        try {
            $context->join(new TimeoutCancellation(0.1));
            self::fail('Joining should have been cancelled');
        } catch (CancelledException $exception) {
            // Expected
        }

        self::assertSame(1, $context->join(new TimeoutCancellation(1)));
    }
}
