<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Deferred;
use Amp\Loop;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Promise;
use Amp\TimeoutException;
use function Amp\call;

class ProcessHub
{
    const PROCESS_START_TIMEOUT = 5000;

    /** @var resource|null */
    private $server;

    /** @var string|null */
    private $uri;

    /** @var string|null */
    private $watcher;

    /** @var Deferred|null */
    private $acceptor;

    public function __construct()
    {
        $isWindows = \strncasecmp(\PHP_OS, "WIN", 3) === 0;

        if ($isWindows) {
            $this->uri = "tcp://localhost:0";
        } else {
            $this->uri = "unix://" . \tempnam(\sys_get_temp_dir(), "amp-parallel-ipc-") . ".sock";
        }

        $this->server = \stream_socket_server($this->uri, $errno, $errstr, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN);

        if (!$this->server) {
            throw new \RuntimeException(\sprintf("Could not create IPC server: (Errno: %d) %s", $errno, $errstr));
        }

        if ($isWindows) {
            $name = \stream_socket_get_name($this->server, false);
            $port = \substr($name, \strrpos($name, ":") + 1);
            $this->uri = "tcp://localhost:" . $port;
        }

        $acceptor = &$this->acceptor;
        $this->watcher = Loop::onReadable($this->server, static function (string $watcher, $server) use (&$acceptor) {
            // Error reporting suppressed since stream_socket_accept() emits E_WARNING on client accept failure.
            if (!$client = @\stream_socket_accept($server, 0)) {  // Timeout of 0 to be non-blocking.
                return; // Accepting client failed.
            }

            $deferred = $acceptor;
            $acceptor = null;
            $deferred->resolve(new ChannelledSocket($client, $client));

            if (!$acceptor) {
                Loop::disable($watcher);
            }
        });

        Loop::disable($this->watcher);
    }

    public function __destruct()
    {
        Loop::cancel($this->watcher);
        \fclose($this->server);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function accept(): Promise
    {
        return call(function () {
            while ($this->acceptor) {
                yield $this->acceptor->promise();
            }

            $this->acceptor = new Deferred;

            Loop::enable($this->watcher);

            try {
                return yield Promise\timeout($this->acceptor->promise(), self::PROCESS_START_TIMEOUT);
            } catch (TimeoutException $exception) {
                Loop::disable($this->watcher);
                throw new ContextException("Starting the process timed out", 0, $exception);
            }
        });
    }
}
