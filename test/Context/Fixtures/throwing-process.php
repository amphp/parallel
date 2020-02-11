<?php

use Amp\Parallel\Sync\Channel;
use Amp\PHPUnit\TestException;

return function (Channel $channel) use ($argv) {
    throw new TestException('Test message');
};
