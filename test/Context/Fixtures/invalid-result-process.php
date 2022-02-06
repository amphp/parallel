<?php

use Amp\Sync\Channel;

return function (Channel $channel) {
    return new class {
        public function __serialize()
        {
            throw new Exception("Cannot serialize");
        }
    };
};
