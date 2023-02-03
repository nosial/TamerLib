<?php 

    /** @noinspection PhpMissingFieldTypeInspection */
    
    namespace Tamer;

    use Closure;
    use InvalidArgumentException;
    use Tamer\Abstracts\Mode;
    use Tamer\Classes\Functions;
    use Tamer\Classes\Validate;
    use Tamer\Exceptions\ConnectionException;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Interfaces\WorkerProtocolInterface;
    use Tamer\Objects\Task;

    class Tamer
    {
        /**
         * The protocol to use when connecting to the server
         *
         * @var string
         */
        private static $protocol;

        /**
         * The protocol to use when connecting to the server as a client
         * 
         * @var ClientProtocolInterface|null
         */
        private static $client;

        /**
         * The protocol to use when connecting to the server as a worker
         * 
         * @var WorkerProtocolInterface|null
         */
        private static $worker;

        /**
         * Indicates if Tamer is running as a client or worker
         *
         * @var string
         * @see Mode
         */
        private static $mode;

        /**
         * Indicates if Tamer is connected to the server
         *
         * @var bool
         */
        private static $connected;

        /**
         * Connects to a server using the specified protocol and mode (client or worker)
         *
         * @param string $protocol
         * @param string $mode
         * @param array $servers
         * @param string|null $username
         * @param string|null $password
         * @return void
         * @throws ConnectionException
         */
        public static function connect(string $protocol, string $mode, array $servers, ?string $username=null, ?string $password=null): void
        {
            if(self::$connected)
            {
                throw new ConnectionException('Tamer is already connected to the server');
            }

            if (!Validate::protocolType($protocol))
            {
                throw new InvalidArgumentException(sprintf('Invalid protocol type: %s', $protocol));
            }

            if (!Validate::mode($mode))
            {
                throw new InvalidArgumentException(sprintf('Invalid mode: %s', $mode));
            }

            self::$protocol = $protocol;
            self::$mode = $mode;

            if (self::$mode === Mode::Client)
            {
                self::$client = Functions::createClient($protocol, $username, $password);
                self::$client->addServers($servers);
                self::$client->connect();
            }
            elseif(self::$mode === Mode::Worker)
            {
                self::$worker = Functions::createWorker($protocol, $username, $password);
                self::$worker->addServers($servers);
                self::$worker->connect();
            }
            else
            {
                throw new InvalidArgumentException(sprintf('Invalid mode: %s', $mode));
            }

            self::$connected = true;
        }


        /**
         * Disconnects from the server
         *
         * @return void
         * @throws ConnectionException
         */
        public static function disconnect(): void
        {
            if (!self::$connected)
            {
                throw new ConnectionException('Tamer is not connected to the server');
            }

            if (self::$mode === Mode::Client)
            {
                self::$client->disconnect();
            }
            else
            {
                self::$worker->disconnect();
            }

            self::$connected = false;
        }

        /**
         * Reconnects to the server
         *
         * @return void
         * @throws ConnectionException
         */
        public static function reconnect(): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$client->reconnect();
            }
            else
            {
                self::$worker->reconnect();
            }
        }

        /**
         * Adds a task to the queue to be executed by the worker
         *
         * @param Task $task
         * @return void
         */
        public static function do(Task $task): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$client->do($task);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Executes a closure operation in the background (does not return a result)
         *
         * @param Closure $closure The closure operation to perform (remote)
         * @return void
         */
        public static function doClosure(Closure $closure): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$client->doClosure($closure);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Queues a task to be processed in parallel (returns a result handled by a callback)
         *
         * @param Task $task
         * @return void
         */
        public static function queue(Task $task): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$client->queue($task);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Queues a closure to be processed in parallel (returns a result handled by a callback)
         *
         * @param Closure $closure The closure operation to perform (remote)
         * @param Closure|null $callback The closure to call when the operation is complete (local)
         * @return void
         */
        public static function queueClosure(Closure $closure, ?Closure $callback=null): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$client->queueClosure($closure, $callback);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Executes all tasks in the queue and waits for them to complete
         *
         * @return bool
         */
        public static function run(): bool
        {
            if (self::$mode === Mode::Client)
            {
                return self::$client->run();
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Registers a function to the worker
         *
         * @param string $name The name of the function to add
         * @param callable $callable The function to add
         * @return void
         */
        public static function addFunction(string $name, callable $callable): void
        {
            if (self::$mode === Mode::Worker)
            {
                self::$worker->addFunction($name, $callable);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in worker mode');
            }
        }

        /**
         * Removes a function from the worker
         *
         * @param string $function_name The name of the function to remove
         * @return void
         */
        public static function removeFunction(string $function_name): void
        {
            if (self::$mode === Mode::Worker)
            {
                self::$worker->removeFunction($function_name);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in worker mode');
            }
        }

        /**
         * Works a job from the queue (blocking or non-blocking)
         *
         * @param bool $blocking (optional) Whether to block until a job is available
         * @param int $timeout (optional) The timeout to use when blocking
         * @param bool $throw_errors (optional) Whether to throw errors or not
         * @return void
         */
        public static function work(bool $blocking=true, int $timeout=500, bool $throw_errors=false): void
        {
            if (self::$mode === Mode::Worker)
            {
                self::$worker->work($blocking, $timeout, $throw_errors);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in worker mode');
            }
        }

        /**
         * @return string
         */
        public static function getProtocol(): string
        {
            return self::$protocol;
        }

        /**
         * @return ClientProtocolInterface|null
         */
        public static function getClient(): ?ClientProtocolInterface
        {
            return self::$client;
        }

        /**
         * @return WorkerProtocolInterface|null
         */
        public static function getWorker(): ?WorkerProtocolInterface
        {
            return self::$worker;
        }

        /**
         * @return string
         */
        public static function getMode(): string
        {
            return self::$mode;
        }

        /**
         * @return bool
         */
        public static function isConnected(): bool
        {
            return self::$connected;
        }
    }