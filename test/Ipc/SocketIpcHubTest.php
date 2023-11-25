<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Ipc;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Parallel\Ipc\SocketIpcHub;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Amp\Socket\ServerSocket;
use Amp\TimeoutCancellation;
use Revolt\EventLoop;
use function Amp\async;

class SocketIpcHubTest extends AsyncTestCase
{
    private ServerSocket $server;
    private SocketIpcHub $ipcHub;

    public function setUp(): void
    {
        parent::setUp();

        $this->server = Socket\listen('127.0.0.1:0');
        $this->ipcHub = new SocketIpcHub($this->server);
    }

    public function testAcceptAfterCancel(): void
    {
        $key = $this->ipcHub->generateKey();

        $deferredCancellation = new DeferredCancellation();
        EventLoop::delay(0.1, static fn () => $deferredCancellation->cancel());

        try {
            $this->ipcHub->accept($key, $deferredCancellation->getCancellation());
            self::fail('Expecting accept to have been cancelled');
        } catch (CancelledException) {
            // Expected accept to be cancelled.
        }

        $key = $this->ipcHub->generateKey();

        async(function () use ($key): void {
            $client = Socket\connect($this->server->getAddress());
            $client->write($key);
        });

        $client = $this->ipcHub->accept($key, new TimeoutCancellation(1));

        self::assertSame($this->server->getAddress()->toString(), $client->getLocalAddress()->toString());
    }
}
