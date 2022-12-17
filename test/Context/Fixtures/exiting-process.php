<?php declare(strict_types=1);

use Amp\Sync\Channel;

return function (Channel $channel) use ($argv) {
    usleep(100);
    exit(1);
};
