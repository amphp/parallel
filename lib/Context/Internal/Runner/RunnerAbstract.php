<?php

namespace Amp\Parallel\Context\Internal\Runner;

use Amp\Parallel\Context\Internal\ProcessHub;
use Amp\Process\ProcessException;
use Amp\Process\ProcessInputStream;
use Amp\Process\ProcessOutputStream;
use Amp\Promise;

abstract class RunnerAbstract
{
    /**
     * Constructor.
     *
     * @param string|array $script Path to PHP script or array with first element as path and following elements options
     *     to the PHP script (e.g.: ['bin/worker', 'Option1Value', 'Option2Value'].
     * @param string $runPath      Path to process runner script
     * @param string $cwd          Current working directory
     * @param array  $env          Environment variables
     * @param string $binary       PHP binary path
     */
    abstract public function __construct($script, string $runPath, ProcessHub $hub, string $cwd = null, array $env = [], string $binary = null);

    /**
     * Set process key.
     *
     * @param string $key Process key
     *
     * @return Promise
     */
    abstract public function setProcessKey(string $key): Promise;

    /**
     * @return bool
     */
    abstract public function isRunning(): bool;

    /**
     * Starts the execution process.
     *
     * @return Promise<int> Resolves with the PID
     */
    abstract public function start(): Promise;

    /**
     * Immediately kills the process.
     */
    abstract public function kill(): void;

    /**
     * @return \Amp\Promise<mixed> Resolves with the returned from the process.
     */
    abstract public function join(): Promise;

    /**
     * Returns the PID of the process.
     *
     * @see \Amp\Process\Process::getPid()
     *
     * @return int
     *
     * @throws \Amp\Process\StatusError
     */
    abstract public function getPid(): int;

    /**
     * Send a signal to the process.
     *
     * @see \Amp\Process\Process::signal()
     *
     * @param int $signo
     *
     * @throws \Amp\Process\ProcessException
     * @throws \Amp\Process\StatusError
     */
    public function signal(int $signo): void
    {
        throw new ProcessException("Not supported!");
    }

    /**
     * Returns the STDIN stream of the process.
     *
     * @see \Amp\Process\Process::getStdin()
     *
     * @return ProcessOutputStream
     *
     * @throws \Amp\Process\StatusError
     */
    public function getStdin(): ProcessOutputStream
    {
        throw new ProcessException("Not supported!");
    }

    /**
     * Returns the STDOUT stream of the process.
     *
     * @see \Amp\Process\Process::getStdout()
     *
     * @return ProcessInputStream
     *
     * @throws \Amp\Process\StatusError
     */
    public function getStdout(): ProcessInputStream
    {
        throw new ProcessException("Not supported!");
    }

    /**
     * Returns the STDOUT stream of the process.
     *
     * @see \Amp\Process\Process::getStderr()
     *
     * @return ProcessInputStream
     *
     * @throws \Amp\Process\StatusError
     */
    public function getStderr(): ProcessInputStream
    {
        throw new ProcessException("Not supported!");
    }
}
