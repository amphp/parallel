<?php

namespace Amp\Parallel\Sync;

use Amp\CallableMaker;
use Amp\Parser\Parser;

final class ChannelParser extends Parser
{
    use CallableMaker;

    const HEADER_LENGTH = 5;

    /**
     * @param callable(mixed $data) Callback invoked when data is parsed.
     */
    public function __construct(callable $callback)
    {
        parent::__construct(self::parser($callback, self::callableFromStaticMethod("errorHandler")));
    }

    /**
     * @param mixed $data Data to encode to send over a channel.
     *
     * @return string Encoded data that can be parsed by this class.
     *
     * @throws \Amp\Parallel\Sync\SerializationException
     */
    public function encode($data): string
    {
        try {
            $data = \serialize($data);
        } catch (\Throwable $exception) {
            throw new SerializationException(
                "The given data cannot be sent because it is not serializable.",
                0,
                $exception
            );
        }

        return \pack("CL", 0, \strlen($data)) . $data;
    }

    /**
     * @param callable $push
     * @param callable $errorHandler
     *
     * @return \Generator
     *
     * @throws \Amp\Parallel\Sync\ChannelException
     * @throws \Amp\Parallel\Sync\SerializationException
     */
    private static function parser(callable $push, callable $errorHandler): \Generator
    {
        while (true) {
            $header = yield self::HEADER_LENGTH;
            $data = \unpack("Cprefix/Llength", $header);

            if ($data["prefix"] !== 0) {
                $data = $header . yield;

                $data = \preg_replace_callback("/[^\x20-\x7e]/", function (array $matches) {
                    return "\\x" . \dechex(\ord($matches[0]));
                }, $data);

                throw new ChannelException("Invalid packet received: " . $data);
            }

            $data = yield $data["length"];

            \set_error_handler($errorHandler);

            // Attempt to unserialize the received data.
            try {
                $data = \unserialize($data);
            } catch (\Throwable $exception) {
                throw new SerializationException("Exception thrown when unserializing data", 0, $exception);
            } finally {
                \restore_error_handler();
            }

            $push($data);
        }
    }

    private static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if ($errno & \error_reporting()) {
            throw new ChannelException(\sprintf(
                'Received corrupted data. Errno: %d; %s in file %s on line %d',
                $errno,
                $errstr,
                $errfile,
                $errline
            ));
        }
    }
}
