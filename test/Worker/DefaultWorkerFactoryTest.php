<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;

class DefaultWorkerFactoryTest extends AsyncTestCase
{
    public function testCreate(): void
    {
        $factory = new ContextWorkerFactory;

        self::assertInstanceOf(Worker::class, $worker = $factory->create());

        $worker->shutdown();
    }

    public function testAutoloading(): void
    {
        $factory = new ContextWorkerFactory(__DIR__ . '/Fixtures/custom-bootstrap.php');

        $worker = $factory->create();

        self::assertTrue($worker->submit(new Fixtures\AutoloadTestTask)->await());

        $worker->shutdown();
    }

    public function testInvalidAutoloaderPath(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('No file found at bootstrap path given');

        $factory = new ContextWorkerFactory(__DIR__ . '/Fixtures/not-found.php');

        $worker = $factory->create();

        $worker->submit(new Fixtures\TestTask(42))->await();
    }
}
