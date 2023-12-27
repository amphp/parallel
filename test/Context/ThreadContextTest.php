<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ThreadContext;
use Amp\Parallel\Context\ThreadContextFactory;

class ThreadContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        if (!ThreadContext::isSupported()) {
            $this->markTestSkipped('ext-parallel required');
        }

        return (new ThreadContextFactory())->start($script);
    }

    public function testExitingProcessOnReceive(): void
    {
        // exit() is not supported in threads.
        $this->expectNotToPerformAssertions();
    }

    public function testExitingProcessOnSend(): void
    {
        // exit() is not supported in threads.
        $this->expectNotToPerformAssertions();
    }

    public function testExitingProcess(): void
    {
        // exit() is not supported in threads.
        $this->expectNotToPerformAssertions();
    }
}
