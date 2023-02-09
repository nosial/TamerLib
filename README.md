# TamerLib

Coming soon...

## Table of Contents

<!-- TOC -->
* [TamerLib](#tamerlib)
  * [Table of Contents](#table-of-contents)
* [Usage](#usage)
  * [Client Usage](#client-usage)
  * [Initialization](#initialization)
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

## Initialization

To use the client, you must first create a connection to the server by running `TamerLib\Tamer::init(string $protocol, array $servers)`
where `$protocol` is the protocol to use (see [Supported Protocols](#supported-protocols)) and `$servers` is an array of 
servers to connect to (eg. `['host:port', 'host:port']`)

```php
TamerLib\Tamer::init(\TamerLib\Abstracts\ProtocolType::Gearman, [
    'host:port', 'host:port'
], $password, $username);
```


## Supported Protocols

 * [x] Gearman
 * [ ] RabbitMQ (Work in progress)
 * [ ] Redis

# License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
