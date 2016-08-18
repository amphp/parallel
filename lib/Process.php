<?php

namespace Amp\Concurrent;

interface Process extends Context {
    /**
     * @return int PID of process.
     */
    public function getPid(): int;

    /**
     * @param int $signo
     *
     * @throws \Amp\Concurrent\StatusError
     */
    public function signal(int $signo);
}
