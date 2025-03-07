<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ForkContextFactory;

class ForkContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        if (!\function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork required');
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
