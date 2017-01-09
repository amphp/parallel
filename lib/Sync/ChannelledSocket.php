<?php

namespace Amp\Parallel\Sync;

use Amp\{ Coroutine, Deferred, Failure, Success };
use Amp\Parallel\{ ChannelException, SerializationException };
use AsyncInterop\{ Loop, Promise };

class ChannelledSocket implements Channel {
    const HEADER_LENGTH = 5;
    
    /** @var resource Stream resource. */
    private $readResource;
    
    /** @var resource Stream resource. */
    private $writeResource;
    
    /** @var string onReadable loop watcher. */
    private $readWatcher;
    
    /** @var string onWritable loop watcher. */
    private $writeWatcher;
    
    /** @var \SplQueue Queue of pending reads. */
    private $reads;
    
    /** @var \SplQueue Queue of pending writes. */
    private $writes;
    
    /** @var bool */
    private $open = true;
    
    /** @var bool */
    private $autoClose = true;
    
    /**
     * @param resource $read Readable stream resource.
     * @param resource $write Writable stream resource.
     * @param bool $autoClose True to close the stream resources when this object is destroyed, false to leave open.
     *
     * @throws \Error If a stream resource is not given for $resource.
     */
    public function __construct($read, $write, bool $autoClose = true) {
        if (!\is_resource($read) || \get_resource_type($read) !== 'stream') {
            throw new \Error('Invalid resource given to constructor!');
        }
    
        if (!\is_resource($write) || \get_resource_type($write) !== 'stream') {
            throw new \Error('Invalid resource given to constructor!');
        }
        
        $this->readResource = $read;
        $this->writeResource = $write;
        $this->autoClose = $autoClose;
        
        \stream_set_blocking($this->readResource, false);
        \stream_set_read_buffer($this->readResource, 0);
        \stream_set_write_buffer($this->readResource, 0);
        
        if ($this->readResource !== $this->writeResource) {
            \stream_set_blocking($this->writeResource, false);
            \stream_set_read_buffer($this->writeResource, 0);
            \stream_set_write_buffer($this->writeResource, 0);
        }
        
        $this->reads = $reads = new \SplQueue;
        $this->writes = $writes = new \SplQueue;
    
        $errorHandler = static function ($errno, $errstr, $errfile, $errline) {
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
        
        $this->readWatcher = Loop::onReadable($this->readResource, static function ($watcher, $stream) use ($reads, $errorHandler) {
            while (!$reads->isEmpty()) {
                /** @var \Amp\Deferred $deferred */
                list($buffer, $length, $deferred) = $reads->shift();
                
                if ($length === 0) {
                    // Error reporting suppressed since fread() produces a warning if the stream unexpectedly closes.
                    $data = @\fread($stream, self::HEADER_LENGTH - \strlen($buffer));
                    
                    if ($data === false || ($data === '' && (\feof($stream) || !\is_resource($stream)))) {
                        $deferred->fail(new ChannelException("The socket unexpectedly closed"));
                        break;
                    }
                    
                    $buffer .= $data;
                    
                    if (\strlen($buffer) !== self::HEADER_LENGTH) {
                        // Not enough data available.
                        $reads->unshift([$buffer, 0, $deferred]);
                        return;
                    }
    
                    $data = \unpack("Cprefix/Llength", $data);
    
                    if ($data["prefix"] !== 0) {
                        $deferred->fail(new ChannelException("Invalid header received"));
                        break;
                    }
                    
                    $length = $data["length"];
                    $buffer = '';
                }
    
                // Error reporting suppressed since fread() produces a warning if the stream unexpectedly closes.
                $data = @\fread($stream, $length - \strlen($buffer));
    
                if ($data === false || ($data === '' && (\feof($stream) || !\is_resource($stream)))) {
                    $deferred->fail(new ChannelException("The socket unexpectedly closed"));
                    break;
                }
                
                $buffer .= $data;
                
                if (\strlen($buffer) < $length) {
                    // Not enough data available.
                    $reads->unshift([$buffer, $length, $deferred]);
                    return;
                }
    
                \set_error_handler($errorHandler);
    
                // Attempt to unserialize the received data.
                try {
                    $data = \unserialize($buffer);
                } catch (\Throwable $exception) {
                    $deferred->fail(new SerializationException("Exception thrown when unserializing data", $exception));
                    continue;
                } finally {
                    \restore_error_handler();
                }
    
                $deferred->resolve($data);
            }
            
            Loop::disable($watcher);
        });
        
        $this->writeWatcher = Loop::onWritable($this->writeResource, static function ($watcher, $stream) use ($writes) {
            while (!$writes->isEmpty()) {
                /** @var \Amp\Deferred $deferred */
                list($data, $previous, $deferred) = $writes->shift();
                $length = \strlen($data);
                
                if ($length === 0) {
                    $deferred->resolve(0);
                    continue;
                }
                
                // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
                $written = @\fwrite($stream, $data);
                
                if ($written === false || $written === 0) {
                    $message = "Failed to write to socket";
                    if ($error = \error_get_last()) {
                        $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                    }
                    $deferred->fail(new ChannelException($message));
                    return;
                }
                
                if ($length <= $written) {
                    $deferred->resolve($written + $previous);
                    continue;
                }
                
                $data = \substr($data, $written);
                $writes->unshift([$data, $written + $previous, $deferred]);
                return;
            }
        });
        
        Loop::disable($this->readWatcher);
        Loop::disable($this->writeWatcher);
    }
    
    public function __destruct() {
        if ($this->readResource !== null) {
            $this->close();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function close() {
        if (\is_resource($this->readResource)) {
            if ($this->autoClose) {
                @\fclose($this->readResource);
                
                if ($this->readResource !== $this->writeResource) {
                    @\fclose($this->writeResource);
                }
            }
            $this->readResource = null;
            $this->writeResource = null;
        }
        
        $this->open = false;
        
        if (!$this->reads->isEmpty()) {
            $exception = new ChannelException("The connection was unexpectedly closed before reading completed");
            do {
                /** @var \Amp\Deferred $deferred */
                list( , , $deferred) = $this->reads->shift();
                $deferred->fail($exception);
            } while (!$this->reads->isEmpty());
        }
        
        if (!$this->writes->isEmpty()) {
            $exception = new ChannelException("The connection was unexpectedly writing completed");
            do {
                /** @var \Amp\Deferred $deferred */
                list( , , $deferred) = $this->writes->shift();
                $deferred->fail($exception);
            } while (!$this->writes->isEmpty());
        }
        
        // defer this, else the Loop::disable() may be invalid
        $read = $this->readWatcher;
        $write = $this->writeWatcher;
        Loop::defer(static function () use ($read, $write) {
            Loop::cancel($read);
            Loop::cancel($write);
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function receive(): Promise {
        if (!$this->open) {
            return new Failure(new ChannelException("The channel is has been closed"));
        }
        
        $deferred = new Deferred;
        $this->reads->push(["", 0, $deferred]);
        
        Loop::enable($this->readWatcher);
        
        return $deferred->promise();
    }
    
    /**
     * @param string $data
     * @param bool $end
     *
     * @return \AsyncInterop\Promise
     */
    public function send($data): Promise {
        if (!$this->open) {
            return new Failure(new ChannelException("The channel is has been closed"));
        }
    
        // Serialize the data to send into the channel.
        try {
            $data = \serialize($data);
        } catch (\Throwable $exception) {
            throw new SerializationException(
                "The given data cannot be sent because it is not serializable.", $exception
            );
        }
        
        $data = \pack("CL", 0, \strlen($data)) . $data;
        $length = \strlen($data);
        $written = 0;
        
        if ($this->writes->isEmpty()) {
            // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
            $written = @\fwrite($this->writeResource, $data);
            
            if ($written === false) {
                $message = "Failed to write to stream";
                if ($error = \error_get_last()) {
                    $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                }
                return new Failure(new ChannelException($message));
            }
            
            if ($length <= $written) {
                return new Success($written);
            }
            
            $data = \substr($data, $written);
        }
        
        return new Coroutine($this->doSend($data, $written));
    }
    
    private function doSend(string $data, int $written): \Generator {
        $deferred = new Deferred;
        $this->writes->push([$data, $written, $deferred]);
    
        Loop::enable($this->writeWatcher);
    
        try {
            $written = yield $deferred->promise();
        } catch (\Throwable $exception) {
            $this->close();
            throw $exception;
        } finally {
            if ($this->writes->isEmpty()) {
                Loop::disable($this->writeWatcher);
            }
        }
    
        return $written;
    }
}