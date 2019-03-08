<?php

namespace Amp\Parallel\Test\Context;

use Amp\Loop;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Parallel;

/**
 * @requires extension parallel
 */
class ParallelTest extends AbstractContextTest
{
    public function createContext($script): Context
    {
        return new Parallel($script);
    }

    public function testGetId()
    {
        Loop::run(function () {
            $context = $this->createContext([
                __DIR__ . "/Fixtures/test-process.php",
                "Test"
            ]);

            yield $context->start();
            $this->assertInternalType('int', $context->getId());
            yield $context->join();

            $context = $this->createContext([
                __DIR__ . "/Fixtures/test-process.php",
                "Test"
            ]);

            $this->expectException(\Error::class);
            $this->expectExceptionMessage('The thread has not been started');

            $context->getId();
        });
    }

    public function testRunStartsThread()
    {
        Loop::run(function () {
            $thread = yield Parallel::run([
                __DIR__ . "/Fixtures/test-process.php",
                "Test"
            ]);

            $this->assertInstanceOf(Parallel::class, $thread);
            $this->assertTrue($thread->isRunning());
            $this->assertInternalType('int', $thread->getId());

            return yield $thread->join();
        });
    }
}
