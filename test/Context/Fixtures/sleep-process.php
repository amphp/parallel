<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel) use ($argv) {
    while (true) {
        usleep(100);
    }
};
