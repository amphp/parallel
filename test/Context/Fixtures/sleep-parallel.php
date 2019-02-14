<?php

use Amp\Delayed;
use Amp\Parallel\Sync\Channel;

return function (Channel $channel, int $time = 1) {
    yield new Delayed($time * 1000);
};
