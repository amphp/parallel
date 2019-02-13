<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel, int $time = null) {
    \sleep($time ?? 1);
};
