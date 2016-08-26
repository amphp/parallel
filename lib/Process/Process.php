<?php declare(strict_types = 1);

namespace Amp\Parallel\Process;

use Amp\Deferred;
use Amp\Parallel\{ ContextException, Process as ProcessContext, StatusError };
use Amp\Socket\Socket;
use Amp\Stream\Stream;
use Interop\Async\{ Awaitable, Loop };

class Process implements ProcessContext {
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
     * @var \Amp\Stream\Stream|null
     */
    private $stdin;

    /**
     * @var \Amp\Stream\Stream|null
     */
    private $stdout;

    /**
     * @var \Amp\Stream\Stream|null
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
     * @var \Amp\Deferred|null
     */
    private $deferred;

    /**
     * @var string
     */
    private $watcher;

    /**
     * @param   string $command Command to run.
     * @param   string|null $cwd Working directory or use an empty string to use the working directory of the current
     *     PHP process.
     * @param   mixed[] $env Environment variables or use an empty array to inherit from the current PHP process.
     * @param   mixed[] $options Options for proc_open().
     */
    public function __construct(string $command, string $cwd = '', array $env = [], array $options = []) {
        $this->command = $command;

        if ($cwd !== '') {
            $this->cwd = $cwd;
        }

        foreach ($env as $key => $value) {
            if (!\is_array($value)) { // $env cannot accept array values.
                $this->env[(string) $key] = (string) $value;
            }
        }

        $this->options = $options;
    }

    /**
     * Stops the process if it is still running.
     */
    public function __destruct() {
        if (\getmypid() === $this->oid) {
            $this->kill(); // Will only terminate if the process is still running.
    
            if ($this->stdin !== null) {
                $this->stdin->close();
            }
    
            if ($this->stdout !== null) {
                $this->stdout->close();
            }
    
            if ($this->stderr !== null) {
                $this->stderr->close();
            }
        }
    }

    /**
     * Resets process values.
     */
    public function __clone() {
        $this->process = null;
        $this->deferred = null;
        $this->watcher = null;
        $this->pid = 0;
        $this->oid = 0;
        $this->stdin = null;
        $this->stdout = null;
        $this->stderr = null;
    }

    /**
     * @throws \Amp\Parallel\ContextException If starting the process fails.
     * @throws \Amp\Parallel\StatusError If the process is already running.
     */
    public function start() {
        if (null !== $this->deferred) {
            throw new StatusError('The process has already been started.');
        }

        $this->deferred = new Deferred;

        $fd = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'a'], // stderr
            ['pipe', 'w'], // exit code pipe
        ];

        $nd = \strncasecmp(\PHP_OS, 'WIN', 3) === 0 ? 'NUL' : '/dev/null';

        $command = \sprintf('(%s) 3>%s; code=$?; echo $code >&3; exit $code', $this->command, $nd);

        $this->process = \proc_open($command, $fd, $pipes, $this->cwd ?: null, $this->env ?: null, $this->options);

        if (!\is_resource($this->process)) {
            throw new ContextException('Could not start process.');
        }

        $this->oid = \getmypid();
        $status = \proc_get_status($this->process);

        if (!$status) {
            \proc_close($this->process);
            $this->process = null;
            throw new ContextException('Could not get process status.');
        }

        $this->pid = $status['pid'];

        $this->stdin = new Socket($pipes[0]);
        $this->stdout = new Socket($pipes[1]);
        $this->stderr = new Socket($pipes[2]);

        $stream = $pipes[3];
        \stream_set_blocking($stream, false);

        $this->watcher = Loop::onReadable($stream, function ($watcher, $resource) {
            if (!\is_resource($resource) || \feof($resource)) {
                $this->close($resource);
                $this->deferred->fail(new ContextException('Process ended unexpectedly.'));
            } else {
                $code = \fread($resource, 1);
                $this->close($resource);
                if (!\strlen($code) || !\is_numeric($code)) {
                    $this->deferred->fail(new ContextException('Process ended without providing a status code.'));
                } else {
                    $this->deferred->resolve((int) $code);
                }
            }
        });
        
        Loop::disable($this->watcher);
    }

    /**
     * Closes the stream resource provided, the open process handle, and stdin.
     *
     * @param resource $resource
     */
    private function close($resource) {
        if (\is_resource($resource)) {
            \fclose($resource);
        }

        if (\is_resource($this->process)) {
            \proc_close($this->process);
            $this->process = null;
        }

        $this->stdin->close();
        
        if ($this->watcher !== null) {
            Loop::cancel($this->watcher);
            $this->watcher = null;
        }
    }

    /**
     * @return \Interop\Async\Awaitable<int> Resolves with exit status.
     *
     * @throws \Amp\Parallel\StatusError If the process has not been started.
     */
    public function join(): Awaitable {
        if ($this->deferred === null) {
            throw new StatusError('The process has not been started.');
        }

        Loop::enable($this->watcher);

        $awaitable = $this->deferred->getAwaitable();
        
        $awaitable->when(function () {
            $this->stdout->close();
            $this->stderr->close();
        });
        
        return $awaitable;
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        if (\is_resource($this->process)) {
            // Forcefully kill the process using SIGKILL.
            \proc_terminate($this->process, 9);

            // "Detach" from the process and let it die asynchronously.
            $this->process = null;
            
            Loop::cancel($this->watcher);
            $this->watcher = null;
        }
    }

    /**
     * Sends the given signal to the process.
     *
     * @param int $signo Signal number to send to process.
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function signal(int $signo) {
        if (!$this->isRunning()) {
            throw new StatusError('The process is not running.');
        }

        \proc_terminate($this->process, (int) $signo);
    }

    /**
     * Returns the PID of the child process. Value is only meaningful if the process has been started and PHP was not
     * compiled with --enable-sigchild.
     *
     * @return int
     */
    public function getPid(): int {
        return $this->pid;
    }

    /**
     * Returns the command to execute.
     *
     * @return string The command to execute.
     */
    public function getCommand(): string {
        return $this->command;
    }

    /**
     * Gets the current working directory.
     *
     * @return string The current working directory or null if inherited from the current PHP process.
     */
    public function getWorkingDirectory(): string {
        if ($this->cwd === '') {
            return \getcwd() ?: '';
        }

        return $this->cwd;
    }

    /**
     * Gets the environment variables array.
     *
     * @return mixed[] Array of environment variables.
     */
    public function getEnv(): array {
        return $this->env;
    }

    /**
     * Gets the options to pass to proc_open().
     *
     * @return mixed[] Array of options.
     */
    public function getOptions(): array {
        return $this->options;
    }

    /**
     * Determines if the process is still running.
     *
     * @return bool
     */
    public function isRunning(): bool {
        return \is_resource($this->process);
    }

    /**
     * Gets the process input stream (STDIN).
     *
     * @return \Amp\Stream\Stream
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function getStdIn(): Stream {
        if ($this->stdin === null) {
            throw new StatusError('The process has not been started.');
        }

        return $this->stdin;
    }

    /**
     * Gets the process output stream (STDOUT).
     *
     * @return \Amp\Stream\Stream
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function getStdOut(): Stream {
        if ($this->stdout === null) {
            throw new StatusError('The process has not been started.');
        }

        return $this->stdout;
    }

    /**
     * Gets the process error stream (STDERR).
     *
     * @return \Amp\Stream\Stream
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function getStdErr(): Stream {
        if ($this->stderr === null) {
            throw new StatusError('The process has not been started.');
        }

        return $this->stderr;
    }
}
