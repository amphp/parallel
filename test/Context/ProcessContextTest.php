<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ProcessContextFactory;

class ProcessContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        return (new ProcessContextFactory())->start($script);
    }
}
