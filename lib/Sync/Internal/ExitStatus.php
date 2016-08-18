<?php

namespace Amp\Concurrent\Sync\Internal;

interface ExitStatus {
    /**
     * @return mixed Return value of the callable given to the execution context.
     *
     * @throws \Amp\Concurrent\PanicError If the context exited with an uncaught exception.
     */
    public function getResult();
}
