<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ParallelContext;
use Amp\Parallel\Context\ParallelContextFactory;

class ParallelContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        if (!ParallelContext::isSupported()) {
            $this->markTestSkipped('ext-parallel required');
        }

        return (new ParallelContextFactory())->start($script);
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
