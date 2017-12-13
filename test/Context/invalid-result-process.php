<?php

use Amp\Parallel\Sync\Channel;

return function (Channel $channel) {
    return new class {
        private function __sleep() {
        }
    };
};
