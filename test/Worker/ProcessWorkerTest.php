<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextFactory;
use Amp\Parallel\Context\ProcessContext;

class ProcessWorkerTest extends AbstractWorkerTest
{
    public function createContextFactory(): ContextFactory
    {
        return new class implements ContextFactory {
            public function start(array|string $script): Context
            {
                return ProcessContext::start($script);
            }
        };
    }
}
