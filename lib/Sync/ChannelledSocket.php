<?php

namespace Amp\Parallel\Sync;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;

class ChannelledSocket extends ChannelledStream
{
    /** @var \Amp\ByteStream\ResourceInputStream */
    private $read;

    /** @var \Amp\ByteStream\ResourceOutputStream */
    private $write;

    /**
     * @param resource $read Readable stream resource.
     * @param resource $write Writable stream resource.
     *
     * @throws \Error If a stream resource is not given for $resource.
     */
    public function __construct($read, $write)
    {
        parent::__construct(
            $this->read = new ResourceInputStream($read),
            $this->write = new ResourceOutputStream($write)
        );
    }

    /**
     * Closes the read and write resource streams.
     */
    public function close()
    {
        $this->read->close();
        $this->write->close();
    }
}
