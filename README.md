# TamerLib

TamerLib is a client that utilizes multiple protocols to allow you to easily send jobs to workers, and receive the results
of those jobs. Basically it's a wrapper around multiple protocols.

```text
                                                 ┌──────────────┐
                                              ┌──► Tamer Worker │
                                              │  └──────────────┘
                                              │
        ┌──────────────┐   ┌────────────────┐ │  ┌──────────────┐
        │              │   │                │ ├──► Tamer Worker │
        │ Tamer Client ◄───┘ Message Server ◄─┘  └──────────────┘
        │              ┌───►                ┌─►
        └──────────────┘   └────────────────┘ │  ┌──────────────┐
                                              ├──► Tamer Worker │
                                              │  └──────────────┘
                                              │
                                              │  ┌──────────────┐
                                              └──► Tamer Worker │
                                                 └──────────────┘
```

It's designed to eliminate the need to write boilerplate code for common tasks, and to allow you to focus on the code
that matters, Tamer will handle the rest even the difficulty of having to use or implement different protocols.


## Table of Contents

<!-- TOC -->
* [TamerLib](#tamerlib)
  * [Table of Contents](#table-of-contents)
* [Usage](#usage)
  * [Client Usage](#client-usage)
    * [Initialization](#initialization)
    * [Sending Jobs](#sending-jobs)
      * [Queued Jobs](#queued-jobs)
      * [Fire & Forget Jobs](#fire--forget-jobs)
  * [Workers](#workers)
    * [Worker Example](#worker-example)
    * [Worker Non-blocking Example](#worker-non-blocking-example)
    * [Executing Workers Independently](#executing-workers-independently)
  * [Supported Protocols](#supported-protocols)
* [License](#license)
<!-- TOC -->

# Usage

Tamer is designed to be simple to use while eliminating the need to write boilerplate code for
common tasks, Tamer can only run as a client or worker on a process, so if you want to run both
you must run two separate processes (but this is also handled by Tamer's builtin supervisor).

The approach Tamer takes is to be out of the way, and to allow you to focus on the code that matters,
Tamer will handle the rest even the difficulty of having to use or implement different protocols.

## Client Usage

Using Tamer as a client allows you to send jobs & closures to workers defined by your client,
and receive the results of those jobs.

### Initialization

To use the client, you must first create a connection to the server by running `TamerLib\Tamer::init(string $protocol, array $servers)`
where `$protocol` is the protocol to use (see [Supported Protocols](#supported-protocols)) and `$servers` is an array of 
servers to connect to (eg. `['host:port', 'host:port']`)

Optionally, you can also pass a username and password to the `init` method, which will be used to authenticate with the
server (such as with RabbitMQ) if the server requires it. You may also just provide a password if a username is not required.

```php
TamerLib\Tamer::init(\TamerLib\Abstracts\ProtocolType::Gearman, [
    'host:port', 'host:port'
], $username, $password);
```

### Sending Jobs

Once you have initialized the client, you can start sending jobs to the workers, jobs are functions ore closures that
workers process and return its result, there are two ways of sending jobs and they both behave differently.

 - Fire & Forget: This is the simplest way of sending a job, it simply sends off the job for a worker to execute and
   immediately forgets about it, this means the job will be executed asynchronously but the client will not receive
   the results of the job.

 - Queued: This is the more advanced way of sending a job, it allows you to queue jobs and then execute them all at once
   by running `TamerLib\Tamer::work()`, this will run all the jobs in the queue in parallel and execute each callback
   accordingly. This is useful if you want to send multiple jobs to a worker and then wait for all of them to finish
   before continuing.


#### Queued Jobs

To send a queued closure to a worker, you can use the `TamerLib\Tamer::queueClosure` method, this method takes two
parameters, the first is a closure that will be executed by the worker, and the second is an optional callback that will
be called in the client side once the worker has finished executing the job, the callback will receive the result of the
job as a parameter.

```php
TamerLib\Tamer::queueClosure(function(){
    // Do something
    return 'Hello World';
}, function($result){
    // Do something with the result
    echo $result; // Hello World
});
```

If you have a worker with defined functions, you can send a queued job to it by using the `TamerLib\Tamer::queueJob` method,
these methods takes one Parameter in the form of a `TamerLib\Objects\Task` object, the task object contains the name of the
function to execute, and the parameters to pass to the function.

You can create a task object by using the `TamerLib\Tamer::create` method, this method takes 3 parameters

 - `(string)` `$function_name` The name of the function to execute
 - `(mixed)` `$data` The data to pass to the function
 - `(Closure|null)` The callback to execute once the worker has finished executing the job

For example, to send a queued task to a worker with a defined function named `sleep` you can do the following:

```php
TamerLib\Tamer::queue(\TamerLib\Objects\Task::create('sleep', 5, function($result){
    echo $result; // 5
}));
```

Once you have queued all the jobs you want to send to the worker, you can execute them all at once by running
`TamerLib\Tamer::work()`, this will run all the jobs in the queue in parallel and execute each callback accordingly.

```php
TamerLib\Tamer::work();
```

#### Fire & Forget Jobs

To send a fire & forget closure to a worker, you can use the `TamerLib\Tamer::doClosure` method, this method takes one
parameter, the closure that will be executed by the worker.

```php
TamerLib\Tamer::doClosure(function(){
    // Do something
    return 'Hello World';
});
```

If you have a worker with defined functions, you can send a fire & forget job to it by using the `TamerLib\Tamer::doJob` method,
this is similar to the `queueJob`, see the [Queued Jobs](#queued-jobs) section for more information.

You can pass on a Task object to the `doJob` method, and the worker will execute the function in the background

```php
  TamerLib\Tamer::doJob(\TamerLib\Objects\Task::create('sleep', 5));
```

 > Note: Fire & Forget jobs do not return a result, so you cannot use a callback with them.


## Workers

Workers are the sub-processes that will handle the jobs sent by the client, they can be defined in any way you want, but
you can also just run simple closures as workers.

```php
TamerLib\Tamer::addWorker('closure', 10);
TamerLib\Tamer::startWorkers();
```

The example above will start 10 closure workers, if you want to run a worker with defined functions you can do so by
passing a class name to the `addWorker` method.

```php
TamerLib\Tamer::addWorker(__DIR__ . DIRECTORY_SEPARATOR . 'my_worker', 10);
TamerLib\Tamer::startWorkers();
```

### Worker Example

In this instance `my_worker` is simply a PHP file that is executed by the supervisor once you run `startWorkers`, the file
looks like this:

```php
<?php

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    TamerLib\Tamer::initWorker();
    
    TamerLib\Tamer::addFunction('sleep', function(\TamerLib\Objects\Job $job) {
            sleep($job->getData());
            return $job->getData();
        });
    
    TamerLib\Tamer::work();
```

This example shows how you can define a sleep function which will make the worker sleep for the amount of seconds
specified in the job data, and then return the amount of seconds it slept for. You can define as many functions as you
want in this file, and they will all be added to the worker.

### Worker Non-blocking Example

Lastly, you must run `TamerLib\Tamer::work()` to start the worker, this will block the current process until the worker
is stopped. If you want to run other code after the worker is started, you can run `TamerLib\Tamer::work(false)` to work
until a timeout is reached, and then continue execution, for example:

```php
while(true)
{
    // Non-blocking for 500 milliseconds, don't throw errors
    TamerLib\Tamer::work(false, 500, false);
    
    // Do other stuff :D
}
```

### Executing Workers Independently

Now you might be wondering, none of these examples show how a worker knows what server to connect to, and their
related credentials, that's because Tamer's supervisor will automatically pass the connection information to the worker
as environment variables, `TAMER_ENABLED`, `TAMER_PROTOCOL`, `TAMER_SERVERS`, `TAMER_USERNAME`, `TAMER_PASSWORD`, and 
`TAMER_INSTANCE_ID`.

But if you want to run additional workers independently (eg; on a different server) you can create the same client and
run it in monitor mode only, this will start the supervisor and the workers, but will not start the client, so you can
run your own client on a different server.

```php
TamerLib\Tamer::init(\TamerLib\Abstracts\ProtocolType::Gearman, [
    'host:port', 'host:port'
], $username, $password);

TamerLib\Tamer::addWorker('closure', 10); // Add 10 closure workers
TamerLib\Tamer::addWorker(__DIR__ . DIRECTORY_SEPARATOR . 'my_worker', 10); // Add 10 worker with defined functions
TamerLib\Tamer::startWorkers(); // Start the workers normally
TamerLib\Tamer::monitor(); // Monitor the workers (blocking)
```

or you can handle it any way you want, just note that the supervisor will shut down the workers if the client is not
running.

## Supported Protocols

 * [x] Gearman
 * [ ] RabbitMQ (Work in progress)
 * [ ] Redis

# License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
