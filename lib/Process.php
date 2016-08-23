<?php declare(strict_types = 1);

namespace Amp\Parallel;

interface Process extends Context {
    /**
     * @return int PID of process.
     */
    public function getPid(): int;

    /**
     * @param int $signo
     *
     * @throws \Amp\Parallel\StatusError
     */
    public function signal(int $signo);
}
