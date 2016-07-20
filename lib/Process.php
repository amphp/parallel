<?php
namespace Icicle\Concurrent;

interface Process extends Context
{
    /**
     * @return int PID of process.
     */
    public function getPid(): int;

    /**
     * @param int $signo
     *
     * @throws \Icicle\Concurrent\Exception\StatusError
     */
    public function signal(int $signo);
}
