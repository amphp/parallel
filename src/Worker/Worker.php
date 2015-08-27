<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\ContextInterface;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Concurrent\Worker\Internal\TaskFailure;

class Worker implements WorkerInterface
{
    /**
     * @var \Icicle\Concurrent\ContextInterface
     */
    private $context;

    /**
     * @var bool
     */
    private $idle = true;

    /**
     * @param \Icicle\Concurrent\ContextInterface $context
     */
    public function __construct(ContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->context->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle()
    {
        return $this->idle;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->context->start();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->context->kill();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        yield $this->context->send([null, []]);

        yield $this->context->join();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(TaskInterface $task /* , ...$args */)
    {
        if (!$this->context->isRunning()) {
            throw new SynchronizationError('Worker has not been started.');
        }

        $args = array_slice(func_get_args(), 1);

        $this->idle = false;

        yield $this->context->send([$task, $args]);

        $result = (yield $this->context->receive());

        $this->idle = true;

        if ($result instanceof TaskFailure) {
            throw $result->getException();
        }

        yield $result;
    }
}