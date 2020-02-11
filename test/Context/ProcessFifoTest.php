<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Process;

/**
 * @requires OS Linux
 */
class ProcessFifoTest extends AbstractContextTest
{
    public function createContext($script): Context
    {
        return new Process($script, null, [], null, true);
    }
}
