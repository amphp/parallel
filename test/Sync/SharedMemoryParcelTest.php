<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Delayed;
use Amp\Parallel\Context\Process;
use Amp\Parallel\Sync\Parcel;
use Amp\Parallel\Sync\SharedMemoryParcel;
use Amp\Sync\SyncException;

/**
 * @requires extension shmop
 * @requires extension sysvmsg
 */
class SharedMemoryParcelTest extends AbstractParcelTest
{
    const ID = __CLASS__;

    private $parcel;

    protected function createParcel($value): Parcel
    {
        $this->parcel = SharedMemoryParcel::create(self::ID, $value);
        return $this->parcel;
    }

    public function tearDown(): void
    {
        $this->parcel = null;
    }

    public function testObjectOverflowMoved(): \Generator
    {
        $object = SharedMemoryParcel::create(self::ID, 'hi', 2);
        yield $object->synchronized(function () {
            return 'hello world';
        });

        $this->assertEquals('hello world', yield $object->unwrap());
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testSetInSeparateProcess(): \Generator
    {
        $object = SharedMemoryParcel::create(self::ID, 42);

        $process = new Process([__DIR__ . '/Fixture/parcel.php', self::ID]);

        $promise = $object->synchronized(function (int $value): \Generator {
            $this->assertSame(42, $value);
            yield new Delayed(500); // Child must wait until parent finishes with parcel.
            return $value + 1;
        });

        yield $process->start();

        $this->assertSame(43, yield $promise);

        $this->assertSame(44, yield $process->join()); // Wait for child process to finish.
        $this->assertEquals(44, yield $object->unwrap());
    }

    public function testInvalidSize(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('size must be greater than 0');

        SharedMemoryParcel::create(self::ID, 42, -1);
    }

    public function testInvalidPermissions(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Invalid permissions');

        SharedMemoryParcel::create(self::ID, 42, 8192, 0);
    }


    public function testNotFound(): void
    {
        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('No semaphore with that ID found');

        SharedMemoryParcel::use('invalid');
    }
}
