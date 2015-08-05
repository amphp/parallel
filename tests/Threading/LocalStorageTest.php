<?php
namespace Icicle\Tests\Concurrent\Threading;

use Icicle\Concurrent\Threading\LocalStorage;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 */
class LocalStorageTest extends TestCase
{
    private $localStorage;

    public function setUp()
    {
        $this->localStorage = new LocalStorage();
    }

    public function testCount()
    {
        $this->localStorage['a'] = 'Apple';
        $this->localStorage['b'] = 'Banana';
        $this->localStorage['c'] = 'Cherry';

        $this->assertCount(3, $this->localStorage);
    }

    public function testIterate()
    {
        $this->localStorage['a'] = 'Apple';
        $this->localStorage['b'] = 'Banana';
        $this->localStorage['c'] = 'Cherry';

        foreach ($this->localStorage as $key => $value) {
            switch ($key) {
                case 'a':
                    $this->assertEquals('Apple', $value);
                    break;
                case 'b':
                    $this->assertEquals('Banana', $value);
                    break;
                case 'c':
                    $this->assertEquals('Cherry', $value);
                    break;
                default:
                    $this->fail('Invalid key returned from iterator.');
            }
        }
    }

    public function testClear()
    {
        $this->localStorage['a'] = 'Apple';
        $this->localStorage['b'] = 'Banana';
        $this->localStorage['c'] = 'Cherry';

        $this->localStorage->clear();
        $this->assertCount(0, $this->localStorage);
        $this->assertEmpty($this->localStorage);
    }

    public function testIsset()
    {
        $this->localStorage['foo'] = 'bar';

        $this->assertTrue(isset($this->localStorage['foo']));
        $this->assertFalse(isset($this->localStorage['baz']));
    }

    public function testGetSet()
    {
        $this->localStorage['foo'] = 'bar';
        $this->assertEquals('bar', $this->localStorage['foo']);
    }

    public function testUnset()
    {
        $this->localStorage['foo'] = 'bar';
        unset($this->localStorage['foo']);

        $this->assertFalse(isset($this->localStorage['foo']));
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testGetInvalidKeyThrowsError()
    {
        $value = $this->localStorage['does not exist'];
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testAppendThrowsError()
    {
        $this->localStorage[] = 'value';
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testSetFloatKeyThrowsError()
    {
        $this->localStorage[1.1] = 'value';
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testSetObjectKeyThrowsError()
    {
        $this->localStorage[new \stdClass()] = 'value';
    }

    public function testClosure()
    {
        $this->localStorage['foo'] = function () {
            return 'Hello, World!';
        };

        $this->assertInstanceOf('Closure', $this->localStorage['foo']);
    }
}
