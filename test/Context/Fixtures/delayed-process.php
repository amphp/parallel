<?php

use Amp\Parallel\Sync\Channel;
use function Revolt\EventLoop\delay;

return function (Channel $channel) use ($argv) {
    delay((int) ($argv[1] ?? 1) * 1000);
};
