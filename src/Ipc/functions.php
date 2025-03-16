<?php declare(strict_types=1);

namespace Amp\Parallel\Ipc;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\Socket\Socket;

/**
 * @param positive-int $keyLength
 */
function readKey(
    ReadableStream|Socket $stream,
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
