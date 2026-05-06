<?php declare(strict_types=1);

namespace App\Worker;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

/**
 * @template-implements Task<string, never, never>
 */
final class FetchTask implements Task
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        $contents = \file_get_contents($this->url);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read from {$this->url}");
        }

        return $contents;
    }
}
