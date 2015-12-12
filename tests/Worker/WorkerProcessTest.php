<?php
namespace Icicle\Tests\Concurrent\Worker;

use Icicle\Concurrent\Worker\WorkerProcessFactory;

class WorkerProcessTest extends AbstractWorkerTest
{
    protected function getFactory()
    {
        return new WorkerProcessFactory();
    }
}
