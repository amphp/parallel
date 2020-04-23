<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskSuccess;
use Amp\PHPUnit\AsyncTestCase;

class TaskSuccessTest extends AsyncTestCase
{
    public function testGetId()
    {
        $id = 'a';
        $result = new TaskSuccess($id, 1);
        $this->assertSame($id, $result->getId());
    }

    public function testPromise()
    {
        $value = 1;
        $result = new TaskSuccess('a', $value);
        $this->assertSame($value, yield $result->promise());
    }
}
