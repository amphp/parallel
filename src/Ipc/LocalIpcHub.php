<?php

namespace Amp\Parallel\Ipc;

use Amp\Cancellation;
use Amp\Socket;
use Amp\Socket\ResourceSocket;
use const Amp\Process\IS_WINDOWS;

final class LocalIpcHub implements IpcHub
{
    private SocketIpcHub $delegate;

    private ?string $toUnlink = null;

    /**
     * @param positive-int $keyLength Length of the random key exchanged on the IPC channel when connecting.
     * @param float $keyReceiveTimeout Timeout to receive the key on accepted connections.
     *
     * @throws Socket\SocketException
     */
    public function __construct(
        int $keyLength = self::DEFAULT_KEY_LENGTH,
        float $keyReceiveTimeout = self::DEFAULT_KEY_RECEIVE_TIMEOUT,
    ) {
        if (IS_WINDOWS) {
            $uri = "tcp://127.0.0.1:0";
        } else {
            $suffix = \bin2hex(\random_bytes(10));
            $path = \sys_get_temp_dir() . "/amp-parallel-ipc-" . $suffix . ".sock";
            $uri = "unix://" . $path;
            $this->toUnlink = $path;
        }

        $this->delegate = new SocketIpcHub(Socket\listen($uri), $keyLength, $keyReceiveTimeout);
    }

    public function accept(string $key, ?Cancellation $cancellation = null): ResourceSocket
    {
        return $this->delegate->accept($key, $cancellation);
    }

    public function isClosed(): bool
    {
        return $this->delegate->isClosed();
    }

    public function close(): void
    {
        $this->delegate->close();
        if ($this->toUnlink !== null) {
            @\unlink($this->toUnlink);
        }
    }

    public function getUri(): string
    {
        return $this->delegate->getUri();
    }

    public function generateKey(): string
    {
        return $this->delegate->generateKey();
    }
}
