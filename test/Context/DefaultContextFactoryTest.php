<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\DefaultContextFactory;
use Amp\PHPUnit\AsyncTestCase;

class DefaultContextFactoryTest extends AsyncTestCase
{
    public function testCreate(): void
    {
        $data = 'factory-test';

        $factory = new DefaultContextFactory;
        $context = $factory->start([__DIR__ . '/Fixtures/test-process.php', $data]);
        self::assertInstanceOf(Context::class, $context);
        self::assertSame($data, $context->join());
    }
}
