<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\BasicEnvironment;
use Amp\PHPUnit\AsyncTestCase;
use function Revolt\EventLoop\delay;

class BasicEnvironmentTest extends AsyncTestCase
{
    public function testBasicOperations(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        self::assertFalse($environment->exists($key));
        self::assertNull($environment->get($key));

        $environment->set($key, 1);
        self::assertTrue($environment->exists($key));
        self::assertSame(1, $environment->get($key));

        $environment->set($key, 2);
        self::assertSame(2, $environment->get($key));

        $environment->delete($key);
        self::assertFalse($environment->exists($key));
        self::assertNull($environment->get($key));
    }

    public function testSetWithNullValue(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        $environment->set($key, null);
        self::assertFalse($environment->exists($key));
    }

    public function testSetShouleThrowError(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The time-to-live must be a positive integer or null');

        $environment = new BasicEnvironment;
        $key = "key";
        $environment->set($key, 1, 0);
    }

    public function testArrayAccess(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        self::assertArrayNotHasKey($key, $environment);
        self::assertNull($environment[$key]);

        $environment[$key] = 1;
        self::assertArrayHasKey($key, $environment);
        self::assertSame(1, $environment[$key]);

        $environment[$key] = 2;
        self::assertSame(2, $environment[$key]);

        unset($environment[$key]);
        self::assertArrayNotHasKey($key, $environment);
        self::assertNull($environment[$key]);
    }

    public function testClear(): void
    {
        $environment = new BasicEnvironment;

        $environment->set("key1", 1);
        $environment->set("key2", 2);

        $environment->clear();

        self::assertFalse($environment->exists("key1"));
        self::assertFalse($environment->exists("key2"));
    }

    public function testTtl(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        $environment->set($key, 1, 2);

        delay(3000);

        self::assertFalse($environment->exists($key));
    }

    /**
     * @depends testTtl
     */
    public function testRemovingTtl(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        $environment->set($key, 1, 1);

        $environment->set($key, 2);

        delay(2000);

        self::assertTrue($environment->exists($key));
        self::assertSame(2, $environment->get($key));
    }

    public function testShorteningTtl(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        $environment->set($key, 1, 10);
        $environment->set($key, 1, 1);

        delay(2000);

        self::assertFalse($environment->exists($key));
    }

    public function testLengtheningTtl(): void
    {
        $environment = new BasicEnvironment;
        $key = "key";

        $environment->set($key, 1, 1);
        $environment->set($key, 1, 3);

        delay(2000);

        self::assertTrue($environment->exists($key));

        delay(1100);

        self::assertFalse($environment->exists($key));
    }

    public function testAccessExtendsTtl(): void
    {
        $environment = new BasicEnvironment;
        $key1 = "key1";
        $key2 = "key2";

        $environment->set($key1, 1, 2);
        $environment->set($key2, 2, 2);

        delay(1000);

        self::assertSame(1, $environment->get($key1));
        self::assertTrue($environment->exists($key2));

        delay(1500);

        self::assertTrue($environment->exists($key1));
        self::assertFalse($environment->exists($key2));

        $environment->delete($key1);
        delay(1000); // Let TTL watcher deactivate itself
    }
}
