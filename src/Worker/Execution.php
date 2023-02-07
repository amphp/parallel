<?php declare(strict_types=1);

namespace Amp\Parallel\Worker;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Sync\Channel;

/**
 * @template-covariant TResult
 * @template TReceive
 * @template TSend
 */
final class Execution
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @param Task<TResult, TReceive, TSend> $task
     * @param Channel<TSend, TReceive> $channel
     * @param Future<TResult> $result
     */
    public function __construct(
        private readonly Task $task,
        private readonly Channel $channel,
        private readonly Future $result,
    ) {
    }

    /**
     * @return Task<TResult, TReceive, TSend>
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * @return Channel<TSend, TReceive>
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }

    /**
     * @return Future<TResult>
     */
    public function getResult(): Future
    {
        return $this->result;
    }
}
