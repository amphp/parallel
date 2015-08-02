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
                          . DIRECTORY_SEPARATOR
                          . spl_object_hash($this)
                          . '.lock';
        touch($this->fileName, time());
    }

    /**
     * {@inheritdoc}
     */
    public function lock()
    {
        $handle = $this->getFileHandle();
        $success = flock($handle, LOCK_EX);
        fclose($handle);

        if (!$success) {
            throw new MutexException("Failed to access the mutex file for locking.")
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unlock()
    {
        $handle = $this->getFileHandle();
        $success = flock($handle, LOCK_UN);
        fclose($handle);

        if (!$success) {
            throw new MutexException("Failed to unlock the mutex file.")
        }
    }

    /**
     * Destroys the mutex.
     */
    public function __destruct()
    {
        @unlink($this->fileName);
    }

    /**
     * Opens the mutex file and returns a file resource.
     *
     * @return resource
     */
    private function getFileHandle()
    {
        $handle = @fopen($this->fileName, 'wb');

        if ($handle === false) {
            throw new MutexException("Failed to open the mutex file.");
        }

        return $handle;
    }
}
