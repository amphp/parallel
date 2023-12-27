<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Cancellation;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\ThreadContext;
use Amp\Parallel\Context\ThreadContextFactory;

class ThreadPoolTest extends AbstractPoolTest
{
    public function createContextFactory(): ContextFactory
    {
        if (!ThreadContext::isSupported()) {
            $this->markTestSkipped('ext-parallel required');
        }

        return new class implements ContextFactory {
            public function start(array|string $script, ?Cancellation $cancellation = null): Context
            {
                return (new ThreadContextFactory())->start($script, cancellation: $cancellation);
            }
        };
    }
}
