<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Cache\Cache;
use Amp\Parallel\Worker\DefaultWorkerFactory;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;

class DefaultWorkerFactoryTest extends AsyncTestCase
{
    public function testInvalidClassName(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Invalid cache class name 'Invalid'");

        $factory = new DefaultWorkerFactory("Invalid");
    }

    public function testNonCacheClassName(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage(\sprintf("does not implement '%s'", Cache::class));

        $factory = new DefaultWorkerFactory(DefaultWorkerFactory::class);
    }

    public function testCreate(): void
    {
        $factory = new DefaultWorkerFactory;

        self::assertInstanceOf(Worker::class, $worker = $factory->create());

        $worker->shutdown();
    }
}
