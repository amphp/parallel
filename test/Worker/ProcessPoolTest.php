<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Cancellation;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\ProcessContextFactory;

/**
 * @group process
 */
class ProcessPoolTest extends AbstractPoolTest
{
    public function createContextFactory(): ContextFactory
    {
        return new class implements ContextFactory {
            public function start(array|string $script, ?Cancellation $cancellation = null): Context
            {
                return (new ProcessContextFactory())->start($script, cancellation: $cancellation);
            }
        };
    }
}
