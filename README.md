# Concurrency Component for Icicle
**Under development.**

Concurrent provides a means of parallelizing code without littering your application with complicated lock checking and inter-process communication.

#### Requirements
- PHP 5.5+
- [pthreads](http://pthreads.org) for multithreading *or*
- System V-compatible UNIX OS and PHP with `--enable-pcntl` and `--enable-sysvsem`

Concurrent can use either process forking or true threading to parallelize execution. Threading provides better performance and is compatible with UNIX and Windows but requires ZTS (Zend thread-safe) PHP, while forking has no external dependencies but is only compatible with UNIX systems. If your environment works meets neither of these requirements, this library won't work.

## Contexts
Concurrent provides a generic interface for working with parallel tasks called "contexts". All contexts are capable of being executed in parallel from the main program code. Each context is able to safely share data stored in member variables of the context object.

### Synchronization
Contexts wouldn't be very useful if they couldn't work on a shared data set. Contexts allow you to share any serializable objects via the properties of a context.

## Threading
Threading is a cross-platform concurrency method that is fast and memory efficient. Thread contexts take advantage of an operating system's multi-threading capabilities to run code in parallel.

## Forking
For UNIX-like systems, you can create parallel execution using fork contexts. Though not as efficient as multi-threading, in some cases forking can take better advantage of some multi-core processors than threads. Fork contexts use the `pcntl_fork()` function to create a copy of the current process and run alternate code inside the new process.

## License
All documentation and source code is licensed under the Apache License, Version 2.0 (Apache-2.0). See the [LICENSE](LICENSE) file for details.
