<?php
namespace Icicle\Concurrent\Process;

use Icicle\Awaitable\Delayed;
use Icicle\Concurrent\Exception\ProcessException;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Process as ProcessContext;
use Icicle\Loop;
use Icicle\Stream\Pipe\ReadablePipe;
use Icicle\Stream\Pipe\WritablePipe;

class Process implements ProcessContext
{
    /**
     * @var resource|null
     */
    private $process;

    /**
     * @var string
     */
    private $command;

    /**
     * @var string
     */
    private $cwd = '';

    /**
     * @var array
     */
    private $env = [];

    /**
     * @var array
     */
    private $options;

    /**
     * @var \Icicle\Stream\Pipe\WritablePipe|null
     */
    private $stdin;

    /**
     * @var \Icicle\Stream\Pipe\ReadablePipe|null
     */
    private $stdout;

    /**
     * @var \Icicle\Stream\Pipe\ReadablePipe|null
     */
    private $stderr;

    /**
     * @var int
     */
    private $pid = 0;

    /**
     * @var int
     */
    private $oid = 0;

    /**
     * @var \Icicle\Awaitable\Delayed|null
     */
    private $delayed;

    /**
     * @var \Icicle\Loop\Watcher\Io|null
     */
    private $poll;

    /**
     * @param   string $command Command to run.
     * @param   string|null $cwd Working directory or use an empty string to use the working directory of the current
     *     PHP process.
     * @param   mixed[] $env Environment variables or use an empty array to inherit from the current PHP process.
     * @param   mixed[] $options Options for proc_open().
     */
    public function __construct($command, $cwd = '', array $env = [], array $options = [])
    {
        $this->command = (string) $command;

        if ('' !== $cwd) {
            $this->cwd = (string) $cwd;
        }

        foreach ($env as $key => $value) {
            if (!is_array($value)) { // $env cannot accept array values.
                $this->env[(string) $key] = (string) $value;
            }
        }

        $this->options = $options;
    }

    /**
     * Stops the process if it is still running.
     */
    public function __destruct()
    {
        if (getmypid() === $this->oid) {
            $this->kill(); // Will only terminate if the process is still running.

            if (null !== $this->stdin) {
                $this->stdin->close();
            }

            if (null !== $this->stdout) {
                $this->stdout->close();
            }

            if (null !== $this->stderr) {
                $this->stderr->close();
            }
        }
    }

    /**
     * Resets process values.
     */
    public function __clone()
    {
        $this->process = null;
        $this->delayed = null;
        $this->poll = null;
        $this->pid = 0;
        $this->oid = 0;
        $this->stdin = null;
        $this->stdout = null;
        $this->stderr = null;
    }

    /**
     * @throws \Icicle\Concurrent\Exception\ProcessException If starting the process fails.
     * @throws \Icicle\Concurrent\Exception\StatusError If the process is already running.
     */
    public function start()
    {
        if (null !== $this->delayed) {
            throw new StatusError('The process has already been started.');
        }

        $this->delayed = new Delayed();

        $fd = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'a'], // stderr
            ['pipe', 'w'], // exit code pipe
        ];

        $nd = 0 === strncasecmp(PHP_OS, 'WIN', 3) ? 'NUL' : '/dev/null';

        $command = sprintf('(%s) 3>%s; code=$?; echo $code >&3; exit $code', $this->command, $nd);

        $this->process = proc_open($command, $fd, $pipes, $this->cwd ?: null, $this->env ?: null, $this->options);

        if (!is_resource($this->process)) {
            throw new ProcessException('Could not start process.');
        }

        $this->oid = getmypid();

        $status = proc_get_status($this->process);

        if (!$status) {
            proc_close($this->process);
            $this->process = null;
            throw new ProcessException('Could not get process status.');
        }

        $this->pid = $status['pid'];

        $this->stdin = new WritablePipe($pipes[0]);
        $this->stdout = new ReadablePipe($pipes[1]);
        $this->stderr = new ReadablePipe($pipes[2]);

        $stream = $pipes[3];
        stream_set_blocking($stream, 0);

        $this->poll = Loop\poll($stream, function ($resource) {
            if (!is_resource($resource) || feof($resource)) {
                $this->delayed->reject(new ProcessException('Process ended unexpectedly.'));
            } else {
                $code = fread($resource, 1);

                if (!strlen($code) || !is_numeric($code)) {
                    $this->delayed->reject(new ProcessException('Process ended without providing a status code.'));
                } else {
                    $this->delayed->resolve((int) $code);
                }
            }

            if (is_resource($resource)) {
                fclose($resource);
            }

            if (is_resource($this->process)) {
                proc_close($this->process);
                $this->process = null;
            }

            $this->stdin->close();
            $this->poll->free();
        });

        $this->poll->unreference();
        $this->poll->listen();
    }

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the process has not been started.
     */
    public function join()
    {
        if (null === $this->delayed) {
            throw new StatusError('The process has not been started.');
        }

        $this->poll->reference();

        try {
            yield $this->delayed;
        } finally {
            $this->stdout->close();
            $this->stderr->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        if (is_resource($this->process)) {
            // Forcefully kill the process using SIGKILL.
            proc_terminate($this->process, 9);

            // "Detach" from the process and let it die asynchronously.
            $this->process = null;
        }
    }

    /**
     * Sends the given signal to the process.
     *
     * @param int $signo Signal number to send to process.
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the process is not running.
     */
    public function signal($signo)
    {
        if (!$this->isRunning()) {
            throw new StatusError('The process is not running.');
        }

        proc_terminate($this->process, (int) $signo);
    }

    /**
     * Returns the PID of the child process. Value is only meaningful if the process has been started and PHP was not
     * compiled with --enable-sigchild.
     *
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Returns the command to execute.
     *
     * @return string The command to execute.
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Gets the current working directory.
     *
     * @return string The current working directory or null if inherited from the current PHP process.
     */
    public function getWorkingDirectory()
    {
        if ('' === $this->cwd) {
            return getcwd() ?: '';
        }

        return $this->cwd;
    }

    /**
     * Gets the environment variables array.
     *
     * @return mixed[] Array of environment variables.
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * Gets the options to pass to proc_open().
     *
     * @return mixed[] Array of options.
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Determines if the process is still running.
     *
     * @return bool
     */
    public function isRunning()
    {
        return is_resource($this->process);
    }

    /**
     * Gets the process input stream (STDIN).
     *
     * @return \Icicle\Stream\WritableStream
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the process is not running.
     */
    public function getStdIn()
    {
        if (null === $this->stdin) {
            throw new StatusError('The process has not been started.');
        }

        return $this->stdin;
    }

    /**
     * Gets the process output stream (STDOUT).
     *
     * @return \Icicle\Stream\ReadableStream
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the process is not running.
     */
    public function getStdOut()
    {
        if (null === $this->stdout) {
            throw new StatusError('The process has not been started.');
        }

        return $this->stdout;
    }

    /**
     * Gets the process error stream (STDERR).
     *
     * @return \Icicle\Stream\ReadableStream
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the process is not running.
     */
    public function getStdErr()
    {
        if (null === $this->stderr) {
            throw new StatusError('The process has not been started.');
        }

        return $this->stderr;
    }
}
