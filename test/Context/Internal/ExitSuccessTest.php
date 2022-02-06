<?php

namespace Amp\Parallel\Test\Context\Internal;

use Amp\Parallel\Context\Internal\ExitSuccess;
use Amp\PHPUnit\AsyncTestCase;

class ExitSuccessTest extends AsyncTestCase
{
    public function testGetResult(): void
    {
        $value = 1;
        $result = new ExitSuccess($value);
        self::assertSame($value, $result->getResult());
    }
}
