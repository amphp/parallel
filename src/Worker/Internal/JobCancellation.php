<?php

namespace Amp\Parallel\Worker\Internal;

final class JobCancellation
{
    public function __construct(
        public /* readonly */ string $id,
    ) {
    }
}
