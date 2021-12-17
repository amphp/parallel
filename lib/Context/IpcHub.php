<?php

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use Revolt\EventLoop;
use function Amp\delay;
use const Amp\Process\IS_WINDOWS;

final class IpcHub
{
    public const KEY_RECEIVE_TIMEOUT = 1;
    public const KEY_LENGTH = 64;

    private int $nextId = 0;

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
        if (IS_WINDOWS) {
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

        if (IS_WINDOWS) {
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
                        $cancellation = new TimeoutCancellation(self::KEY_RECEIVE_TIMEOUT);

                        try {
                            $received = self::readKey($client, $cancellation);
                        } catch (\Throwable) {
                            \fclose($client);
                            return; // Ignore possible foreign connection attempt.
                        }

                        if (!isset($keys[$received])) {
                            \fclose($client);
                            return; // Ignore possible foreign connection attempt.
                        }

                        $id = $keys[$received];

                        $deferred = $acceptor[$id];
                        unset($acceptor[$id], $keys[$received]);
                        $deferred->complete($client);
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

    public function getUri(): string
    {
        return $this->uri;
    }

    public function generateKey(): string
    {
        return \random_bytes(self::KEY_LENGTH);
    }

    /**
     * @param Cancellation|null $cancellation
     *
     * @return resource
     * @throws ContextException
     */
    public function accept(string $key, ?Cancellation $cancellation = null)
    {
        $id = $this->nextId++;

        $this->keys[$key] = $id;
        $this->acceptor[$id] = $deferred = new DeferredFuture;

        EventLoop::enable($this->watcher);

        try {
            $pair = $deferred->getFuture()->await($cancellation);
        } catch (CancelledException $exception) {
            unset($this->acceptor[$id], $this->keys[$key]);
            throw new ContextException("Starting the process timed out", 0, $exception);
        } finally {
            if (empty($this->acceptor)) {
                EventLoop::disable($this->watcher);
            }
        }

        return $pair;
    }

    /**
     * Note this is designed to be used in the child process/thread and performs a blocking read of the
     * hub key from the given stream resource.
     *
     * @param resource $stream
     * @param Cancellation|null $cancellation Closes the stream if cancelled.
     */
    public static function readKey($stream, ?Cancellation $cancellation = null): string
    {
        $key = "";

        $id = $cancellation?->subscribe(static fn () => \fclose($stream));

        try {
            // Read random key from $stream and send back to parent over IPC socket to authenticate.
            do {
                if (($chunk = \fread($stream, self::KEY_LENGTH - \strlen($key))) === false || \feof($stream)) {
                    throw new \RuntimeException("Could not read key from parent", E_USER_ERROR);
                }
                $key .= $chunk;
            } while (\strlen($key) < self::KEY_LENGTH);

            return $key;
        } finally {
            $cancellation?->unsubscribe($id);
        }
    }

    /**
     * Note that this is designed to be used in the child process/thread and performs a blocking connect.
     *
     * @return resource
     */
    public static function connect(string $uri, string $key, float $timeout = 5)
    {
        $connectStart = microtime(true);

        while (!$client = \stream_socket_client($uri, $errno, $errstr, $timeout, \STREAM_CLIENT_CONNECT)) {
            if (microtime(true) > $connectStart + $timeout) {
                throw new \RuntimeException("Could not connect to IPC socket", \E_USER_ERROR);
            }

            delay(0.01);
        }

        \fwrite($client, $key);

        return $client;
    }
}
