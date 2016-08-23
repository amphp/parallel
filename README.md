# Parallel Processing for Amp

**True parallel processing using native threading and multiprocessing for parallelizing code, *without* blocking.**

This library is a component for [Amp](https://amphp.org) that provides native threading, multiprocessing, process synchronization, shared memory, and task workers. Like other Amp components, this library uses Coroutines built from Awaitables and [Generators](http://www.php.net/manual/en/language.generators.overview.php) to make writing asynchronous code more like writing synchronous code.

This library provides a means of parallelizing code without littering your application with complicated lock checking and inter-process communication.

To be as flexible as possible, this library comes with a collection of non-blocking concurrency tools that can be used independently as needed, as well as an "opinionated" worker API that allows you to assign units of work to a pool of worker threads or processes.

##### Requirements

- PHP 7 (no extensions required)

##### Suggested

- [pthreads extension](https://pecl.php.net/package/pthreads): Best extension option for concurrency in PHP, but it requires PHP to be compiled with `--enable-maintainer-zts` to enable thread-safety.
- [pcntl extension](http://php.net/manual/en/book.pcntl.php): Enables forking concurrency method.
- [sysvmsg extension](http://php.net/manual/en/book.sem.php): Required for sharing memory between forks or processes.

##### Installation

The recommended way to install is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use this package in your project:

```bash
composer require amphp/parallel
```

You can also manually edit `composer.json` to add this library as a project requirement.

```js
// composer.json
{
    "require": {
        "amphp/parallel": "dev-master"
    }
}
```

### Development and Contributing

Want to hack on the source? A [Vagrant](http://vagrantup.com) box is provided with the repository to give a common development environment for running concurrent threads and processes, and comes with a bunch of handy tools and scripts for testing and experimentation.

Starting up and logging into the virtual machine is as simple as

    vagrant up && vagrant ssh

Once inside the VM, you can install PHP extensions with [Pickle](https://github.com/FriendsOfPHP/pickle), switch versions with `newphp VERSION`, and test for memory leaks with [Valgrind](http://valgrind.org).
