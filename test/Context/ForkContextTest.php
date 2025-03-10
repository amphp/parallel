<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ForkContext;
use Amp\Parallel\Context\ForkContextFactory;

class ForkContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        if (!ForkContext::isSupported()) {
            $this->markTestSkipped('Not supported on the current platform/driver');
        }

        return (new ForkContextFactory())->start($script);
    }

    public function testThrowingProcessOnReceive(): void
    {
        // tmp
        $this->expectNotToPerformAssertions();
    }

    public function testThrowingProcessOnSend(): void
    {
        // tmp
        $this->expectNotToPerformAssertions();
    }
}
