<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

/**
 * An internal thread that executes a given function concurrently.
 */
class Thread extends \Thread
{
    const MSG_DONE = 1;
    const MSG_ERROR = 2;

    private $socket;

    /**
     * @var ThreadContext An instance of the context local to this thread.
     */
    public $context;

    /**
     * @var string|null Path to an autoloader to include.
     */
    public $autoloaderPath;

    /**
     * @var callable The function to execute in the thread.
     */
    private $function;

    /**
     * Creates a new thread object.
     *
     * @param callable $function The function to execute in the thread.
     */
    public function __construct(callable $function)
    {
        $this->function = $function;
        $this->context = ThreadContext::createLocalInstance($this);
    }

    public function initialize($socket)
    {
        $this->socket = $socket;
    }

    public function run()
    {
        try {
            if (file_exists($this->autoloaderPath)) {
                require $this->autoloaderPath;
            }

            $generator = call_user_func($this->function);
            if ($generator instanceof \Generator) {
                $coroutine = new Coroutine($generator);
            }

            Loop\run();

            $this->sendMessage(self::MSG_DONE);
        } catch (\Exception $exception) {
            print $exception . PHP_EOL;
            $this->sendMessage(self::MSG_ERROR);
            $serialized = serialize($exception);
            $length = strlen($serialized);
            fwrite($this->socket, pack('S', $length) . $serialized);
        } finally {
            fclose($this->socket);
        }
    }

    private function sendMessage($message)
    {
        fwrite($this->socket, chr($message));
    }
}
