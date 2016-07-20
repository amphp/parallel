<?php
namespace Icicle\Concurrent\Sync\Internal;

interface ExitStatus
{
    /**
     * @return mixed Return value of the callable given to the execution context.
     *
     * @throws \Icicle\Concurrent\Exception\PanicError If the context exited with an uncaught exception.
     */
    public function getResult();
}
