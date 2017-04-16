<?php

namespace Amp\Parallel\Sync;

use Amp\{ Coroutine, Promise };
use Amp\ByteStream\{ Parser, ReadableStream, StreamException, WritableStream };
use Amp\Parallel\{ ChannelException, SerializationException };

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

    /** @var \SplQueue */
    private $received;

    /** @var \Amp\ByteStream\Parser */
    private $parser;

    /**
     * Creates a new channel from the given stream objects. Note that $read and $write can be the same object.
     *
     * @param \Amp\ByteStream\ReadableStream $read
     * @param \Amp\ByteStream\WritableStream $write
     */
    public function __construct(ReadableStream $read, WritableStream $write) {
        $this->read = $read;
        $this->write = $write;
        $this->received = new \SplQueue;
        $this->parser = new Parser(self::parser($this->received, static function ($errno, $errstr, $errfile, $errline) {
            if ($errno & \error_reporting()) {
                throw new ChannelException(\sprintf(
                    'Received corrupted data. Errno: %d; %s in file %s on line %d',
                    $errno,
                    $errstr,
                    $errfile,
                    $errline
                ));
            }
        }));
    }

    /**
     * @param \SplQueue $queue
     * @param callable $errorHandler
     *
     * @return \Generator
     *
     * @throws \Amp\Parallel\ChannelException
     * @throws \Amp\Parallel\SerializationException
     */
    private static function parser(\SplQueue $queue, callable $errorHandler): \Generator {
        while (true) {
            $data = \unpack("Cprefix/Llength", yield self::HEADER_LENGTH);

            if ($data["prefix"] !== 0) {
                throw new ChannelException("Invalid header received");
            }

            $data = yield $data["length"];

            \set_error_handler($errorHandler);

            // Attempt to unserialize the received data.
            try {
                $data = \unserialize($data);
            } catch (\Throwable $exception) {
                throw new SerializationException("Exception thrown when unserializing data", $exception);
            } finally {
                \restore_error_handler();
            }

            $queue->push($data);
        }
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
        } catch (StreamException $exception) {
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
        while ($this->received->isEmpty()) {
            if (!yield $this->read->advance()) {
                throw new ChannelException("The channel closed. Did the context die?");
            }

            try {
                yield $this->parser->write($this->read->getChunk());
            } catch (StreamException $exception) {
                throw new ChannelException("Reading from the channel failed. Did the context die?", $exception);
            }
        }

        return $this->received->shift();
    }
}
