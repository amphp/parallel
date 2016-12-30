<?php

namespace Amp\Parallel\Sync\Internal;

interface ExitStatus {
    /**
     * @return mixed Return value of the callable given to the execution context.
     *
     * @throws \Amp\Parallel\PanicError If the context exited with an uncaught exception.
     */
    public function getResult();
}
