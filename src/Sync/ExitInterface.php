<?php
namespace Icicle\Concurrent\Sync;

interface ExitInterface
{
    /**
     * @return mixed Return value of the callable given to the execution context.
     *
     * @throws \Icicle\Concurrent\Exception\PanicError If the context exited with an uncaught exception.
     */
    public function getResult();
}
