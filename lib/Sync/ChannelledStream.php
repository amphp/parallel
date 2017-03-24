<?php

namespace Amp\Parallel\Sync;

use Amp\{ Coroutine, Promise };
use Amp\Parallel\{ ChannelException, SerializationException };
use Amp\ByteStream\{ DuplexStream, ReadableStream, WritableStream };

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 */
class ChannelledStream implements Channel {
    const HEADER_LENGTH = 5;

    /** @var \Amp\ByteStream\ReadableStream */
    private $read;

    /** @var \Amp\ByteStream\WritableStream */
    private $write;

    /** @var \Closure */
    private $errorHandler;

    /**
     * Creates a new channel instance.
     *
     * @param \Amp\ByteStream\ReadableStream $read
     * @param \Amp\ByteStream\WritableStream|null $write
     */
    public function __construct(ReadableStream $read, WritableStream $write = null) {
        if ($write === null) {
            if (!$read instanceof DuplexStream) {
                throw new \TypeError('Must provide a duplex stream if no write stream is given');
            }
            $this->write = $read;
        } else {
            $this->write = $write;
        }

        $this->read = $read;

        $this->errorHandler = static function ($errno, $errstr, $errfile, $errline) {
            if ($errno & \error_reporting()) {
                throw new ChannelException(\sprintf(
                    'Received corrupted data. Errno: %d; %s in file %s on line %d',
                    $errno,
                    $errstr,
                    $errfile,
                    $errline
                ));
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Promise {
        return new Coroutine($this->doSend($data));
    }
    
    private function doSend($data): \Generator {
        // Serialize the data to send into the channel.
        try {
            $data = \serialize($data);
        } catch (\Throwable $exception) {
            throw new SerializationException(
                "The given data cannot be sent because it is not serializable.", $exception
            );
        }

        try {
            return yield $this->write->write(\pack("CL", 0, \strlen($data)) . $data);
        } catch (\Throwable $exception) {
            throw new ChannelException("Sending on the channel failed. Did the context die?", $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): Promise {
        return new Coroutine($this->doReceive());
    }
    
    private function doReceive(): \Generator {
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
