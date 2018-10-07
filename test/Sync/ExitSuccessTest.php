<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\ExitSuccess;
use Amp\PHPUnit\TestCase;

class ExitSuccessTest extends TestCase
{
    public function testGetResult()
    {
        $value = 1;
        $result = new ExitSuccess($value);
        $this->assertSame($value, $result->getResult());
    }
}
