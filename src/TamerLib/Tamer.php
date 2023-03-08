<?php 

    /** @noinspection PhpMissingFieldTypeInspection */
    
    namespace TamerLib;

    use Closure;
    use Exception;
    use InvalidArgumentException;
    use TamerLib\Abstracts\Mode;
    use TamerLib\Classes\Configuration;
    use TamerLib\Classes\Functions;
    use TamerLib\Classes\Supervisor;
    use TamerLib\Classes\Validate;
    use TamerLib\Exceptions\ConnectionException;
    use TamerLib\Exceptions\UnsupervisedWorkerException;
    use TamerLib\Interfaces\ClientProtocolInterface;
    use TamerLib\Interfaces\WorkerProtocolInterface;
    use TamerLib\Objects\Task;

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
         * @var string|null
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
         * The supervisor that is supervising the workers
         *
         * @var Supervisor
         */
        private static $supervisor;

        /**
         * Initializes Tamer as a client and connects to the server
         *
         * @deprecated @param string $protocol
         * @deprecated @param array $servers
         * @deprecated @param string|null $username
         * @deprecated @param string|null $password
         * @return void
         * @throws ConnectionException
         */
        public static function init(): void
        {
            if(self::$connected)
            {
                throw new ConnectionException('Tamer is already connected to the server');
            }

            $configuration = Configuration::getConfiguration();

            if (!Validate::protocolType($configuration['protocol']))
            {
                throw new InvalidArgumentException(sprintf('Invalid protocol type: %s', $configuration['protocol']));
            }

            self::$protocol = $configuration['protocol'];
            self::$mode = Mode::Client;
            self::$client = Functions::createClient(
                $configuration['protocol'],
                $configuration['username'], $configuration['password']
            );
            self::$client->addServers($configuration['servers']);
            self::$client->connect();
            self::$supervisor = new Supervisor(
                $configuration['protocol'], $configuration['servers'],
                $configuration['username'], $configuration['password']
            );
            self::$connected = true;
        }

        /**
         * Initializes Tamer as a worker client and connects to the server
         *
         * @return void
         * @throws ConnectionException
         * @throws UnsupervisedWorkerException
         */
        public static function initWorker(): void
        {
            if(self::$connected)
            {
                throw new ConnectionException('Tamer is already connected to the server');
            }

            if(!Functions::getWorkerVariables()['TAMER_ENABLED'])
            {
                throw new UnsupervisedWorkerException('Tamer is not enabled for this worker');
            }

            self::$protocol = Functions::getWorkerVariables()['TAMER_PROTOCOL'];
            self::$mode = Mode::Worker;
            self::$worker = Functions::createWorker(self::$protocol);
            self::$worker->addServers(Functions::getWorkerVariables()['TAMER_SERVERS']);
            self::$worker->connect();
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
         * @noinspection PhpUnused
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
         * @noinspection PhpUnused
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
         * @noinspection PhpUnused
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
         * Monitors the workers and restarts them if they die unexpectedly (monitor mode only)
         *
         * @param bool $blocking
         * @param bool $auto_restart
         * @return void
         * @throws Exception
         * @noinspection PhpUnused
         */
        public static function monitor(bool $blocking=false, bool $auto_restart=true): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$supervisor->monitor($blocking, $auto_restart);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Adds a worker to the supervisor
         *
         * @param string $target
         * @param int $instances
         * @return void
         * @throws Exception
         */
        public static function addWorker(string $target, int $instances): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$supervisor->addWorker($target, $instances);
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Starts all workers
         *
         * @return void
         * @throws Exception
         */
        public static function startWorkers(): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$supervisor->start();
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Stops all workers
         *
         * @return void
         * @throws Exception
         * @noinspection PhpUnused
         */
        public static function stopWorkers(): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$supervisor->stop();
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * Restarts all workers
         *
         * @return void
         * @throws Exception
         * @noinspection PhpUnused
         */
        public static function restartWorkers(): void
        {
            if (self::$mode === Mode::Client)
            {
                self::$supervisor->restart();
            }
            else
            {
                throw new InvalidArgumentException('Tamer is not running in client mode');
            }
        }

        /**
         * @return string
         * @noinspection PhpUnused
         */
        public static function getProtocol(): string
        {
            return self::$protocol;
        }

        /**
         * @return ClientProtocolInterface|null
         * @noinspection PhpUnused
         */
        public static function getClient(): ?ClientProtocolInterface
        {
            return self::$client;
        }

        /**
         * @return WorkerProtocolInterface|null
         * @noinspection PhpUnused
         */
        public static function getWorker(): ?WorkerProtocolInterface
        {
            return self::$worker;
        }

        /**
         * Returns the current mode of TamerLib
         *
         * @return string
         * @noinspection PhpUnused
         */
        public static function getMode(): string
        {
            if(self::$mode == null)
                return Mode::None;

            return self::$mode;
        }

        /**
         * Returns True  if the client is connected to the server
         *
         * @return bool
         */
        public static function isConnected(): bool
        {
            return self::$connected;
        }
    }