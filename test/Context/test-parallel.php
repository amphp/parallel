<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel, string $data): string {
    return $data;
};
