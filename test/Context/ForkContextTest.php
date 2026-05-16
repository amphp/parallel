<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ForkContext;
use Amp\Parallel\Context\ForkContextFactory;

/**
 * @requires extension pcntl
 * @requires extension posix
 */
class ForkContextTest extends AbstractContextTest
{
    public function createContext(string|array $script): Context
    {
        if (!ForkContext::isSupported()) {
            $this->markTestSkipped('Not supported on the current platform/driver');
        }

        return (new ForkContextFactory())->start($script);
    }
}
