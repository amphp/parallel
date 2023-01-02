<?php declare(strict_types=1);

namespace Amp\Parallel\Ipc;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Cancellation;
use Amp\Socket;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketConnector;
use Revolt\EventLoop;

/**
 * Gets or sets the global shared IpcHub instance.
 *
 * @param IpcHub|null $ipcHub If not null, set the global shared IpcHub to this instance.
 */
function ipcHub(?IpcHub $ipcHub = null): IpcHub
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($ipcHub) {
        return $map[$driver] = $ipcHub;
    }

    return $map[$driver] ??= new LocalIpcHub();
}

/**
 * Note this is designed to be used in the child process/thread.
 *
 * @param Cancellation|null $cancellation Closes the stream if cancelled.
 * @param positive-int $keyLength
 */
function readKey(
    ReadableResourceStream|Socket\Socket $stream,
    ?Cancellation $cancellation = null,
    int $keyLength = SocketIpcHub::DEFAULT_KEY_LENGTH,
): string {
    $key = "";

    // Read random key from $stream and send back to parent over IPC socket to authenticate.
    do {
        /** @psalm-suppress InvalidArgument */
        if (($chunk = $stream->read($cancellation, $keyLength - \strlen($key))) === null) {
            throw new \RuntimeException("Could not read key from parent", E_USER_ERROR);
        }
        $key .= $chunk;
    } while (\strlen($key) < $keyLength);

    return $key;
}

/**
 * Note that this is designed to be used in the child process/thread to connect to an IPC socket.
 */
function connect(
    string $uri,
    string $key,
    ?Cancellation $cancellation = null,
    ?SocketConnector $connector = null,
): EncryptableSocket {
    $connector ??= Socket\socketConnector();

    $client = $connector->connect($uri, cancellation: $cancellation);
    $client->write($key);

    return $client;
}
