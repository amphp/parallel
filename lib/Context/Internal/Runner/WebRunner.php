<?php

namespace Amp\Parallel\Context;

use Amp\Parallel\Context\Internal\ProcessHub;
use Amp\Parallel\Context\Internal\Runner\RunnerAbstract;
use Amp\Promise;
use Amp\Success;

final class WebRunner extends RunnerAbstract
{
    /**
     * PID.
     *
     * @var int
     */
    private $pid;
    /**
     * Initialization payload.
     *
     * @var array
     */
    private $params;
    /**
     * Whether the process is running.
     *
     * @var boolean
     */
    private $running = false;
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
    public function __construct($script, string $runPath, ProcessHub $hub, string $cwd = null, array $env = [], string $binary = null)
    {
        if (!isset($_SERVER['SERVER_NAME'])) {
            throw new ContextException("Could not initialize web runner!");
        }

        if (!\is_array($script)) {
            $script = [$script];
        }
        $this->params = [
            'options' => [
                "html_errors" => "0",
                "display_errors" => "0",
                "log_errors" => "1",
            ],
            'argv' => [
                $hub->getUri(),
                ...$script
            ]
        ];
        $this->pid = \random_int(0, PHP_INT_MAX);
    }


    /**
     * Returns the PID of the process.
     *
     * @see \Amp\Process\Process::getPid()
     *
     * @return int
     *
     * @throws \Amp\Process\StatusError
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * Set process key.
     *
     * @param string $key Process key
     *
     * @return Promise
     */
    public function setProcessKey(string $key): Promise
    {
        $this->params['key'] = $key;
        $params = \http_build_query($params);

        $address = ($_SERVER['HTTPS'] ?? false ? 'tls' : 'tcp').'://'.$_SERVER['SERVER_NAME'];
        $port = $_SERVER['SERVER_PORT'];
        $uri = $_SERVER['REQUEST_URI'];
        $params = $_GET;

        $url = \explode('?', $uri, 2)[0] ?? '';
        $query = \http_build_query($params);
        $uri = \implode('?', [$url, $query]);

        $this->payload = "GET $uri HTTP/1.1\r\nHost: ${_SERVER['SERVER_NAME']}\r\n\r\n";

        $a = \fsockopen($address, $port);
        \fwrite($a, $payload);

        $this->running =true;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Starts the process.
     *
     * @return Promise<int> Resolved with the PID
     */
    public function start(): Promise
    {
        return new Success();
    }

    /**
     * Immediately kills the process.
     */
    public function kill(): void
    {
        $this->process->kill();
    }

    /**
     * @return \Amp\Promise<mixed> Resolves with the returned from the process.
     */
    public function join(): Promise
    {
        return new Success();
    }
}
