<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Context\ContextPanicError;
use Amp\Parallel\Context\Internal\ExitFailure;
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
        } catch (ContextPanicError $caught) {
            self::assertGreaterThan(0, \stripos($caught->getMessage(), $message));
            return;
        }

        self::fail(\sprintf("Exception should be thrown from %s::getResult()", ExitFailure::class));
    }
}
