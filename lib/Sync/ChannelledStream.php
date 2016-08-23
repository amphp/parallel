<?php declare(strict_types = 1);

namespace Amp\Parallel\Sync;

use Amp\Coroutine;
use Amp\Parallel\{ ChannelException, SerializationException };
use Amp\Stream\Stream;
use Interop\Async\Awaitable;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 */
class ChannelledStream implements Channel {
    const HEADER_LENGTH = 5;

    /**
     * @var \Amp\Stream\Stream
     */
    private $read;

    /**
     * @var \Amp\Stream\Stream
     */
    private $write;

    /**
     * @var \Closure
     */
    private $errorHandler;

    /**
     * Creates a new channel instance.
     *
     * @param \Amp\Stream\Stream $read
     * @param \Amp\Stream\Stream|null $write
     */
    public function __construct(Stream $read, Stream $write = null) {
        if ($write === null) {
            $this->write = $read;
        } else {
            $this->write = $write;
        }

        $this->read = $read;

        $this->errorHandler = static function ($errno, $errstr) {
            throw new ChannelException(\sprintf('Received corrupted data. Errno: %d; %s', $errno, $errstr));
        };
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Awaitable {
        return new Coroutine($this->doSend($data));
    }
    
    public function doSend($data): \Generator {
        // Serialize the data to send into the channel.
        try {
            $serialized = \serialize($data);
        } catch (\Throwable $exception) {
            throw new SerializationException(
                "The given data cannot be sent because it is not serializable.", $exception
            );
        }

        $length = \strlen($serialized);

        try {
            yield $this->write->write(\pack("CL", 0, $length) . $serialized);
        } catch (\Throwable $exception) {
            throw new ChannelException("Sending on the channel failed. Did the context die?", $exception);
        }
        
        return $length;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): Awaitable {
        return new Coroutine($this->doReceive());
    }
    
    public function doReceive(): \Generator {
        try {
            // Read the message length first to determine how much needs to be read from the stream.
            $buffer = yield $this->read->read(self::HEADER_LENGTH);
            
            $data = \unpack("Cprefix/Llength", $buffer);

            if ($data["prefix"] !== 0) {
                throw new ChannelException("Invalid header received");
            }
            
            $buffer = yield $this->read->read($data["length"]);
        } catch (\Throwable $exception) {
            throw new ChannelException("Reading from the channel failed. Did the context die?", $exception);
        }

        \set_error_handler($this->errorHandler);

        // Attempt to unserialize the received data.
        try {
            $data = \unserialize($buffer);
        } catch (\Throwable $exception) {
            throw new SerializationException("Exception thrown when unserializing data", $exception);
        } finally {
            \restore_error_handler();
        }

        return $data;
    }
}
