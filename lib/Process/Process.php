<?php declare(strict_types = 1);

namespace Amp\Parallel\Process;

use Amp\Deferred;
use Amp\Parallel\{ ContextException, Process as ProcessContext, StatusError };
use Interop\Async\{ Awaitable, Loop };

class Process implements ProcessContext {
    /** @var resource|null */
    private $process;

    /** @var string */
    private $command;

    /** @var string */
    private $cwd = "";

    /** @var array */
    private $env = [];

    /** @var array */
    private $options;
    
    /** @var resource|null */
    private $stdin;

    /** @var resource|null */
    private $stdout;

    /** @var resource|null */
    private $stderr;

    /** @var int */
    private $pid = 0;

    /** @var int */
    private $oid = 0;

    /** @var \Amp\Deferred|null */
    private $deferred;

    /** @var string */
    private $watcher;

    /**
     * @param   string $command Command to run.
     * @param   string|null $cwd Working directory or use an empty string to use the working directory of the current
     *     PHP process.
     * @param   mixed[] $env Environment variables or use an empty array to inherit from the current PHP process.
     * @param   mixed[] $options Options for proc_open().
     */
    public function __construct(string $command, string $cwd = null, array $env = [], array $options = []) {
        $this->command = $command;

        if ($cwd !== null) {
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
    
            if (\is_resource($this->stdin)) {
                \fclose($this->stdin);
            }
    
            if (\is_resource($this->stdout)) {
                \fclose($this->stdout);
            }
    
            if (\is_resource($this->stderr)) {
                \fclose($this->stderr);
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
        if ($this->deferred !== null) {
            throw new StatusError("The process has already been started");
        }

        $this->deferred = $deferred = new Deferred;

        $fd = [
            ["pipe", "r"], // stdin
            ["pipe", "w"], // stdout
            ["pipe", "a"], // stderr
            ["pipe", "w"], // exit code pipe
        ];

        $nd = \strncasecmp(\PHP_OS, "WIN", 3) === 0 ? "NUL" : "/dev/null";

        $command = \sprintf('(%s) 3>%s; code=$?; echo $code >&3; exit $code', $this->command, $nd);

        $this->process = @\proc_open($command, $fd, $pipes, $this->cwd ?: null, $this->env ?: null, $this->options);

        if (!\is_resource($this->process)) {
            $message = "Could not start process";
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            throw new ContextException($message);
        }

        $this->oid = \getmypid();
        $status = \proc_get_status($this->process);

        if (!$status) {
            \proc_close($this->process);
            $this->process = null;
            throw new ContextException("Could not get process status");
        }

        $this->pid = $status["pid"];

        $this->stdin = $stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        $stream = $pipes[3];
        \stream_set_blocking($stream, false);

        $process = &$this->process;
        
        $this->watcher = Loop::onReadable($stream, static function ($watcher, $resource) use (
            &$process, $deferred, $stdin
        ) {
            try {
                try {
                    if (!\is_resource($resource) || \feof($resource)) {
                        throw new ContextException("Process ended unexpectedly");
                    }
                    $code = @\fread($resource, 1);
                    if (!\strlen($code) || !\is_numeric($code)) {
                        throw new ContextException("Process ended without providing a status code");
                    }
                } finally {
                    if (\is_resource($resource)) {
                        \fclose($resource);
                    }
                    if (\is_resource($process)) {
                        \proc_close($process);
                        $process = null;
                    }
                    if (\is_resource($stdin)) {
                        \fclose($stdin);
                    }
                    Loop::cancel($watcher);
                }
                
                $deferred->resolve((int) $code);
            } catch (\Throwable $exception) {
                $deferred->fail($exception);
            }
        });
        
        Loop::disable($this->watcher);
    }

    /**
     * @return \Interop\Async\Awaitable<int> Resolves with exit status.
     *
     * @throws \Amp\Parallel\StatusError If the process has not been started.
     */
    public function join(): Awaitable {
        if ($this->deferred === null) {
            throw new StatusError("The process has not been started");
        }

        Loop::enable($this->watcher);

        \fclose($this->stdout);
        \fclose($this->stderr);

        return $this->deferred->getAwaitable();
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
            
            $this->deferred->fail(new ContextException("The process was killed"));
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
            throw new StatusError("The process is not running");
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
        if ($this->cwd === "") {
            return \getcwd() ?: "";
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
     * @return resource
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function getStdIn() {
        if ($this->stdin === null) {
            throw new StatusError("The process has not been started");
        }

        return $this->stdin;
    }

    /**
     * Gets the process output stream (STDOUT).
     *
     * @return resource
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function getStdOut() {
        if ($this->stdout === null) {
            throw new StatusError("The process has not been started");
        }

        return $this->stdout;
    }

    /**
     * Gets the process error stream (STDERR).
     *
     * @return resource
     *
     * @throws \Amp\Parallel\StatusError If the process is not running.
     */
    public function getStdErr() {
        if ($this->stderr === null) {
            throw new StatusError("The process has not been started");
        }

        return $this->stderr;
    }
}
