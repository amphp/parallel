<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\ChannelParser;
use Amp\PHPUnit\TestCase;

class ChannelParserTest extends TestCase {
    /**
     * @expectedException \Amp\Parallel\Sync\SerializationException
     * @expectedExceptionMessage Exception thrown when unserializing data
     */
    public function testCorruptedData() {
        $data = "Invalid serialized data";
        $data = \pack("CL", 0, \strlen($data)) . $data;
        $parser = new ChannelParser($this->createCallback(0));
        $parser->push($data);
    }
}
