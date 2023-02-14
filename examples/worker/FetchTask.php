<?php

namespace App\Worker;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

final class FetchTask implements Task
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        return \file_get_contents($this->url);
    }
}