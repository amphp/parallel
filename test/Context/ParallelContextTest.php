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
}
