<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel, int $time = 1) {
    \sleep($time);
};
