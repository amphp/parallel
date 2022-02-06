<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\DefaultContextFactory;
use Amp\PHPUnit\AsyncTestCase;

class DefaultContextFactoryTest extends AsyncTestCase
{
    public function testCreate(): void
    {
        $factory = new DefaultContextFactory;
        $context = $factory->start(__DIR__ . '/Fixtures/test-process.php');
        self::assertInstanceOf(Context::class, $context);
    }
}
