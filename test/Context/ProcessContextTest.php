<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ProcessContext;

class ProcessContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        return ProcessContext::start($script);
    }
}
