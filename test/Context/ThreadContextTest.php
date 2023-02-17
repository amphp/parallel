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
        $this->markTestSkipped('exit in thread is buggy');
    }

    public function testExitingProcessOnSend(): void
    {
        $this->markTestSkipped('exit in thread is buggy');
    }
}
