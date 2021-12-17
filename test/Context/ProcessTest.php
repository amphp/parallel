<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Process;

class ProcessTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        return Process::start($script);
    }
}
