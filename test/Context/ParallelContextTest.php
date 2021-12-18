<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ParallelContext;

/**
 * @requires extension parallel
 */
class ParallelContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        return ParallelContext::start($script);
    }

    public function testGetId()
    {
        $context = $this->createContext([
            __DIR__ . "/Fixtures/test-process.php",
            "Test",
        ]);

        self::assertIsInt($context->getId());
        $context->join();

        $context = $this->createContext([
            __DIR__ . "/Fixtures/test-process.php",
            "Test",
        ]);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The thread has not been started');

        $context->getId();
    }
}
