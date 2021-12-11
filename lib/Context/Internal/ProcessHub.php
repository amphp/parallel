<?php

namespace Amp\Parallel\Context\Internal;

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\TimeoutCancellation;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

class ProcessHub
{
    private const PROCESS_START_TIMEOUT = 5;
    private const KEY_RECEIVE_TIMEOUT = 1;

    /** @var resource|null */
    private $server;

    private string $uri;

    /** @var int[] */
    private array $keys = [];

    /** @var string|null */
    private ?string $watcher;

    /** @var DeferredFuture[] */
    private array $acceptor = [];

    private ?string $toUnlink = null;

    public function __construct()
    {
        $isWindows = \PHP_OS_FAMILY === 'Windows';

        if ($isWindows) {
            $this->uri = "tcp://127.0.0.1:0";
        } else {
            $suffix = \bin2hex(\random_bytes(10));
            $path = \sys_get_temp_dir() . "/amp-parallel-ipc-" . $suffix . ".sock";
            $this->uri = "unix://" . $path;
            $this->toUnlink = $path;
        }

        $context = \stream_context_create([
            'socket' => ['backlog' => 128],
        ]);

        $this->server = \stream_socket_server(
            $this->uri,
            $errno,
            $errstr,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
            $context
        );

        if (!$this->server) {
            throw new \RuntimeException(\sprintf("Could not create IPC server: (Errno: %d) %s", $errno, $errstr));
        }

        if ($isWindows) {
            $name = \stream_socket_get_name($this->server, false);
            $port = \substr($name, \strrpos($name, ":") + 1);
            $this->uri = "tcp://127.0.0.1:" . $port;
        }

        $keys = &$this->keys;
        $acceptor = &$this->acceptor;
        $this->watcher = EventLoop::onReadable(
            $this->server,
            static function (string $watcher, $server) use (&$keys, &$acceptor): void {
                // Error reporting suppressed since stream_socket_accept() emits E_WARNING on client accept failure.
                while ($client = @\stream_socket_accept($server, 0)) {  // Timeout of 0 to be non-blocking.
                    EventLoop::queue(static function () use ($client, &$keys, &$acceptor): void {
                        $channel = new ChannelledSocket($client, $client);

                        try {
                            $received = async(fn () => $channel->receive())
                                ->await(new TimeoutCancellation(self::KEY_RECEIVE_TIMEOUT));
                        } catch (\Throwable) {
                            $channel->close();
                            return; // Ignore possible foreign connection attempt.
                        }

                        if (!\is_string($received) || !isset($keys[$received])) {
                            $channel->close();
                            return; // Ignore possible foreign connection attempt.
                        }

                        $pid = $keys[$received];

                        $deferred = $acceptor[$pid];
                        unset($acceptor[$pid], $keys[$received]);
                        $deferred->complete($channel);
                    });
                }
            }
        );

        EventLoop::disable($this->watcher);
    }

    public function __destruct()
    {
        EventLoop::cancel($this->watcher);
        \fclose($this->server);
        if ($this->toUnlink !== null) {
            @\unlink($this->toUnlink);
        }
    }

    final public function getUri(): string
    {
        return $this->uri;
    }

    final public function generateKey(int $pid, int $length): string
    {
        $key = \random_bytes($length);
        $this->keys[$key] = $pid;
        return $key;
    }

    final public function accept(int $pid, float $timeout = self::PROCESS_START_TIMEOUT): ChannelledSocket
    {
        $this->acceptor[$pid] = new DeferredFuture;

        EventLoop::enable($this->watcher);

        try {
            $channel = $this->acceptor[$pid]
                ->getFuture()
                ->await(new TimeoutCancellation($timeout));
        } catch (CancelledException $exception) {
            $key = \array_search($pid, $this->keys, true);
            \assert(\is_string($key), "Key for {$pid} not found");
            unset($this->acceptor[$pid], $this->keys[$key]);
            throw new ContextException("Starting the process timed out", 0, $exception);
        } finally {
            if (empty($this->acceptor)) {
                EventLoop::disable($this->watcher);
            }
        }

        return $channel;
    }

    /**
     * Note this is designed to be used in the child process/thread and performs a blocking read of the
     * hub key from the given stream resource.
     *
     * @param resource $stream
     */
    public static function readKey($stream, int $keyLength): string
    {
        $key = "";

        // Read random key from $stream and send back to parent over IPC socket to authenticate.
        do {
            if (($chunk = \fread($stream, $keyLength - strlen($key))) === false || \feof($stream)) {
                throw new \RuntimeException("Could not read key from parent", E_USER_ERROR);
            }
            $key .= $chunk;
        } while (\strlen($key) < $keyLength);

        return $key;
    }

    /**
     * Note that this is designed to be used in the child process/thread and performs a blocking connect.
     */
    public static function connect(string $uri, float $timeout = 5): ChannelledSocket
    {
        $connectStart = microtime(true);

        while (!$socket = \stream_socket_client($uri, $errno, $errstr, $timeout, \STREAM_CLIENT_CONNECT)) {
            if (microtime(true) > $connectStart + $timeout) {
                throw new \RuntimeException("Could not connect to IPC socket", \E_USER_ERROR);
            }

            delay(0.01);
        }

        return new ChannelledSocket($socket, $socket);
    }
}
