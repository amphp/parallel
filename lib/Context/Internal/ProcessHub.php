<?php

namespace Amp\Parallel\Context\Internal;

use Amp\Deferred;
use Amp\Loop;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Promise;
use function Amp\call;

class ProcessHub
{
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
        $this->uri = "unix://" . \tempnam(\sys_get_temp_dir(), "amp-cluster-ipc-") . ".sock";
        $this->server = \stream_socket_server($this->uri, $errno, $errstr, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN);

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
                Loop::unreference($watcher);
            }
        });

        Loop::unreference($this->watcher);
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

            Loop::reference($this->watcher);
            Loop::enable($this->watcher);

            return $this->acceptor->promise();
        });
    }
}
