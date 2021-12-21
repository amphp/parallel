<?php

namespace Amp\Parallel\Worker;

use Amp\Future;
use Amp\Parallel\Sync\Channel;

final class Job
{
    public function __construct(
        private Task $task,
        private Channel $channel,
        private Future $future,
    ) {
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getFuture(): Future
    {
        return $this->future;
    }
}
