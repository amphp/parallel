<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\Internal\ParallelContext;

/**
 * @requires extension parallel
 */
class ParallelWorkerTest extends AbstractWorkerTest
{
    public function createContextFactory(): ContextFactory
    {
        return new class implements ContextFactory {
            public function start(array|string $script): Context
            {
                return ParallelContext::start($script);
            }
        };
    }
}
