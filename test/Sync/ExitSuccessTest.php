<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\ExitSuccess;
use Amp\PHPUnit\AsyncTestCase;

class ExitSuccessTest extends AsyncTestCase
{
    public function testGetResult()
    {
        $value = 1;
        $result = new ExitSuccess($value);
        $this->assertSame($value, $result->getResult());
    }
}
