<?php declare(strict_types=1);

use Amp\PHPUnit\TestException;
use Amp\Sync\Channel;

return function (Channel $channel) use ($argv) {
    throw new TestException('Test message');
};
