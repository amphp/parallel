<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Context\Process;
use Amp\Parallel\Sync\Parcel;
use Amp\Parallel\Sync\SharedMemoryException;
use Amp\Parallel\Sync\SharedMemoryParcel;
use Amp\Sync\SyncException;
use function Amp\delay;
use function Amp\async;

/**
 * @requires extension shmop
 * @requires extension sysvmsg
 */
class SharedMemoryParcelTest extends AbstractParcelTest
{
    const ID = __CLASS__;

    private ?SharedMemoryParcel $parcel;

    public function tearDown(): void
    {
        $this->parcel = null;
    }

    public function testObjectOverflowMoved(): \Generator
    {
        $object = SharedMemoryParcel::create(self::ID, 'hi', 2);
        $object->synchronized(function () {
            return 'hello world';
        });

        self::assertEquals('hello world', yield $object->unwrap());
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testSetInSeparateProcess(): void
    {
        $object = SharedMemoryParcel::create(self::ID, 42);

        $process = new Process([__DIR__ . '/Fixture/parcel.php', self::ID]);

        $promise = async(fn () => $object->synchronized(function (int $value): int {
            $this->assertSame(42, $value);
            delay(0.5); // Child must wait until parent finishes with parcel.
            return $value + 1;
        }));

        $process->start();

        self::assertSame(43, $promise->await());

        self::assertSame(44, $process->join()); // Wait for child process to finish.
        self::assertEquals(44, $object->unwrap());
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

    public function testDoubleCreate(): void
    {
        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('A semaphore with that ID already exists');

        $parcel1 = SharedMemoryParcel::create(self::ID, 42);
        $parcel2 = SharedMemoryParcel::create(self::ID, 42);
    }

    public function testTooBig(): void
    {
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage('Failed to create shared memory block');

        SharedMemoryParcel::create(self::ID, 42, 1 << 50);
    }

    protected function createParcel($value): Parcel
    {
        $this->parcel = SharedMemoryParcel::create(self::ID, $value);
        return $this->parcel;
    }
}
