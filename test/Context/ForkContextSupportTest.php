<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\ForkContext;
use Amp\Parallel\Context\ForkContextFactory;
use Amp\Parallel\Ipc\IpcHub;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;

/**
 * Runs in separate processes, as the driver can only be replaced before the event loop runs.
 *
 * @requires extension pcntl
 * @requires extension posix
 * @requires function pcntl_fork
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ForkContextSupportTest extends TestCase
{
    public function testStartWithDriverWithNativeHandle(): void
    {
        EventLoop::setDriver($this->createDriverWithNativeHandle());

        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Forking requires');

        ForkContext::start($this->createUnusedIpcHub(), __DIR__ . '/Fixtures/test-process.php');
    }

    public function testFactoryStartWithDriverWithNativeHandle(): void
    {
        EventLoop::setDriver($this->createDriverWithNativeHandle());

        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('Forking requires');

        (new ForkContextFactory(ipcHub: $this->createUnusedIpcHub()))->start(__DIR__ . '/Fixtures/test-process.php');
    }

    private function createDriverWithNativeHandle(): Driver
    {
        $driver = $this->createMock(Driver::class);
        $driver->expects(self::atLeastOnce())->method('getHandle')->willReturn(new \stdClass());

        return $driver;
    }

    private function createUnusedIpcHub(): IpcHub
    {
        $ipcHub = $this->createMock(IpcHub::class);
        $ipcHub->expects(self::never())->method('generateKey');

        return $ipcHub;
    }
}
