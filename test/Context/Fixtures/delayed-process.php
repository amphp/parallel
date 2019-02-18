<?php

use Amp\Delayed;
use Amp\Parallel\Sync\Channel;

return function (Channel $channel) use ($argv) {
    yield new Delayed((int) ($argv[1] ?? 1) * 1000);
};
