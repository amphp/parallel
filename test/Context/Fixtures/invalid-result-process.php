<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel) {
    return new class {
        public function __sleep()
        {
            throw new Exception("Cannot serialize");
        }
    };
};
