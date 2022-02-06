<?php

namespace Amp\Parallel\Ipc;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Cancellation;
use Amp\Socket;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\ResourceSocket;
use Amp\Socket\SocketConnector;
use Revolt\EventLoop;

/**
 * Gets the global shared IpcHub instance.
 *
 * @return IpcHub
 */
function ipcHub(): IpcHub
{
    static $hubs;
    $hubs ??= new \WeakMap();
    return $hubs[EventLoop::getDriver()] ??= new LocalIpcHub();
}

/**
 * Note this is designed to be used in the child process/thread.
 *
 * @param ReadableResourceStream|ResourceSocket $stream
 * @param Cancellation|null $cancellation Closes the stream if cancelled.
 * @param positive-int $keyLength
 */
function readKey(
    ReadableResourceStream|ResourceSocket $stream,
    ?Cancellation $cancellation = null,
    int $keyLength = IpcHub::DEFAULT_KEY_LENGTH,
): string {
    $key = "";

    // Read random key from $stream and send back to parent over IPC socket to authenticate.
    do {
        if (($chunk = $stream->read($cancellation, $keyLength - \strlen($key))) === null) {
            throw new \RuntimeException("Could not read key from parent", E_USER_ERROR);
        }
        $key .= $chunk;
    } while (\strlen($key) < $keyLength);

    return $key;
}

/**
 * Note that this is designed to be used in the child process/thread and performs a blocking connect.
 *
 * @return EncryptableSocket
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
