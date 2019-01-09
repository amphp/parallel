<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel) use ($argv) {
    \sleep((int) ($argv[1] ?? 1));
};
