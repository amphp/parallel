<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context\Internal;

use Amp\Parallel\Context\ContextException;
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
        } catch (ContextException $caught) {
            self::assertGreaterThan(0, \stripos($caught->getMessage(), $message));
            return;
        }

        self::fail(\sprintf("Exception should be thrown from %s::getResult()", ExitFailure::class));
    }
}
