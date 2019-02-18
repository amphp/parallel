<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Parallel;

/**
 * @requires extension parallel
 */
class ParallelTest extends AbstractContextTest
{
    public function createContext($script): Context
    {
        return new Parallel($script);
    }
}
