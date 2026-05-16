<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use PHPUnit\Framework\TestCase;
use function Amp\Parallel\Context\flattenArgument;

class FlattenArgumentTest extends TestCase
{
    public function testNan()
    {
        self::assertSame('NAN', flattenArgument(\sqrt(-1)));
    }
}
