<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\ExitFailure;
use Amp\Parallel\Sync\PanicError;
use Amp\PHPUnit\AsyncTestCase;

class ExitFailureTest extends AsyncTestCase
{
    public function testGetResult()
    {
        $message = "Test message";
        $exception = new \Exception($message);
        $result = new ExitFailure($exception);
        try {
            $result->getResult();
        } catch (PanicError $caught) {
            $this->assertGreaterThan(0, \stripos($caught->getMessage(), $message));
            return;
        }

        $this->fail(\sprintf("Exception should be thrown from %s::getResult()", ExitFailure::class));
    }
}
