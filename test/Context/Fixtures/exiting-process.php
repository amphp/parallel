<?php

use Amp\Sync\Channel;

return function (Channel $channel) use ($argv) {
    \usleep(100);
    exit(1);
};
