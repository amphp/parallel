<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\MutexException;

/**
 * A cross-platform mutex that uses file locking as the lock mechanism.
 *
 * This mutex implementation is not always atomic and depends on the operating
 * system's implementation of file locking operations. Use this implementation
 * only if no other mutex types are available.
 *
 * @see http://php.net/manual/en/function.flock.php
 */
class FileMutex implements MutexInterface
{
    /**
     * @var string The full path to the lock file.
     */
    private $fileName;

    /**
     * Creates a new mutex.
     */
    public function __construct()
    {
        $this->fileName = sys_get_temp_dir()
                          .DIRECTORY_SEPARATOR
                          .spl_object_hash($this)
                          .'.lock';
        touch($this->fileName, time());
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        $handle = $this->getFileHandle();

        // Try to access the lock. If we can't get the lock, set an asynchronous
        // timer and try again.
        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
        }

        // Return a lock object that can be used to release the lock on the mutex.
        yield new Lock(function (Lock $lock) {
            $this->release();
        });

        fclose($handle);
    }

    /**
     * Destroys the mutex.
     */
    public function __destruct()
    {
        @unlink($this->fileName);
    }

    /**
     * Releases the lock on the mutex.
     *
     * @throws MutexException If the unlock operation failed.
     */
    protected function release()
    {
        $handle = $this->getFileHandle();
        $success = flock($handle, LOCK_UN);
        fclose($handle);

        if (!$success) {
            throw new MutexException('Failed to unlock the mutex file.');
        }
    }

    /**
     * Opens the mutex file and returns a file resource.
     *
     * @return resource
     *
     * @throws MutexException If the mutex file could not be opened.
     */
    private function getFileHandle()
    {
        $handle = @fopen($this->fileName, 'wb');

        if ($handle === false) {
            throw new MutexException('Failed to open the mutex file.');
        }

        return $handle;
    }
}
