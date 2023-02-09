<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Protocols\RabbitMq;

    use Exception;
    use LogLib\Log;
    use PhpAmqpLib\Message\AMQPMessage;
    use TamerLib\Abstracts\JobStatus;
    use TamerLib\Abstracts\ObjectType;
    use TamerLib\Classes\Validate;
    use TamerLib\Exceptions\ConnectionException;
    use TamerLib\Interfaces\WorkerProtocolInterface;
    use TamerLib\Objects\Job;
    use TamerLib\Objects\JobResults;

    class Worker implements WorkerProtocolInterface
    {
        /**
         * An array of defined servers to use
         *
         * @var array
         */
        private $defined_servers;

        /**
         * @var false
         */
        private $automatic_reconnect;

        /**
         * An array of functions that the worker handles
         *
         * @var array
         */
        private $functions;

        /**
         * (Optional) The username to use when connecting to the server (if required)
         *
         * @var string|null
         */
        private $username;

        /**
         * (Optional) The password to use when connecting to the server
         *
         * @var string|null
         */
        private $password;

        /**
         * A array of active connections
         *
         * @var Connection[]
         */
        private $connections;

        /**
         * @var array
         */
        private $options;

        /**
         * Public Constructor with optional username and password
         *
         * @param string|null $username
         * @param string|null $password
         */
        public function __construct(?string $username = null, ?string $password = null)
        {
            $this->defined_servers = [];
            $this->connections = [];
            $this->functions = [];
            $this->automatic_reconnect = true;
            $this->username = $username;
            $this->password = $password;
        }

        /**
         * Adds a server to the list of servers to use
         *
         * @param string $host
         * @param int $port
         * @return void
         */
        public function addServer(string $host, int $port): void
        {
            if(!isset($this->defined_servers[$host]))
            {
                $this->defined_servers[$host] = [];
            }

            if(in_array($port, $this->defined_servers[$host]))
            {
                return;
            }

            $this->defined_servers[$host][] = $port;
        }

        /**
         * Adds an array of servers to the list of servers to use
         *
         * @param array $servers (eg; [host:port, host:port, ...])
         * @return void
         */
        public function addServers(array $servers): void
        {
            foreach($servers as $server)
            {
                $server = explode(':', $server);
                $this->addServer($server[0], (int)$server[1]);
            }
        }

        /**
         * Establishes a connection to the server (or servers)
         *
         * @return void
         * @noinspection DuplicatedCode
         * @throws ConnectionException
         */
        public function connect(): void
        {
            if($this->isConnected())
                return;

            if(count($this->defined_servers) === 0)
                return;

            foreach($this->defined_servers as $host => $ports)
            {
                foreach($ports as $port)
                {
                    $connection = new Connection($host, $port, $this->username, $this->password);
                    $connection->connect();

                    $this->connections[] = $connection;
                }
            }
        }

        /**
         * Disconnects from the server
         *
         * @return void
         */
        public function disconnect(): void
        {
            if(!$this->isConnected())
                return;

            foreach($this->connections as $connection)
            {
                $connection->disconnect();
            }

            $this->connections = [];
        }

        /**
         * Reconnects to the server (or servers)
         *
         * @return void
         * @throws ConnectionException
         */
        public function reconnect(): void
        {
            $this->disconnect();
            $this->connect();
        }

        /**
         * Returns True if one or more connections are connected, False otherwise
         * (Note, some connections may be disconnected, and this will still return True)
         *
         * @return bool
         */
        public function isConnected(): bool
        {
            if(count($this->connections) === 0)
                return false;

            foreach($this->connections as $connection)
            {
                if($connection->isConnected())
                    return true;
            }

            return false;
        }

        /**
         * Sets the options to use for this client
         *
         * @param array $options
         * @return void
         */
        public function setOptions(array $options): void
        {
            $this->options = $options;
        }

        /**
         * Returns the current options for this client
         *
         * @return array
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        /**
         * Clears the current options for this client
         *
         * @return void
         */
        public function clearOptions(): void
        {
            $this->options = [];
        }

        /**
         * Returns True if automatic reconnection is enabled, False otherwise
         *
         * @return bool
         */
        public function automaticReconnectionEnabled(): bool
        {
            return $this->automatic_reconnect;
        }

        /**
         * Enables or disables automatic reconnection
         *
         * @param bool $enable
         * @return void
         */
        public function enableAutomaticReconnection(bool $enable): void
        {
            $this->automatic_reconnect = $enable;
        }

        /**
         * Registers a new function to the worker to handle
         *
         * @param string $name
         * @param callable $callable
         * @param mixed|null $context
         * @return void
         */
        public function addFunction(string $name, callable $callable, mixed $context = null): void
        {
            $this->functions[$name] = [
                'function' => $callable,
                'context' => $context
            ];
        }

        /**
         * Removes an existing function from the worker
         *
         * @param string $function_name
         * @return void
         */
        public function removeFunction(string $function_name): void
        {
            unset($this->functions[$function_name]);
        }

        /**
         * Processes a job if there's one available
         *
         * @param bool $blocking
         * @param int $timeout
         * @param bool $throw_errors
         * @return void
         */
        public function work(bool $blocking = true, int $timeout = 500, bool $throw_errors = false): void
        {
            if(!$this->isConnected())
                return;

            // Select a random connection
            $connection = $this->connections[array_rand($this->connections)];

            $callback = function($message) use ($throw_errors, $connection)
            {
                var_dump(Validate::getObjectType(msgpack_unpack($message->body)));
                if(Validate::getObjectType(msgpack_unpack($message->body)) !== ObjectType::Job)
                {
                    $connection->getChannel()->basic_nack($message->delivery_info['delivery_tag']);
                    return;
                }

                $received_job = Job::fromArray(msgpack_unpack($message->body));

                if($received_job->isClosure())
                {
                    Log::debug('net.nosial.tamerlib', 'received closure: ' . $received_job->getId());

                    try
                    {
                        // TODO: Check back on this, looks weird.
                        $closure = $received_job->getData();
                        $result = $closure($received_job);
                    }
                    catch(Exception $e)
                    {
                        unset($e);

                        // Do not requeue the job, it's a closure
                        $connection->getChannel()->basic_nack($message->delivery_info['delivery_tag']);
                        return;
                    }

                    $job_results = new JobResults($received_job, JobStatus::Success, $result);
                    $connection->getChannel->basic_publish(
                        new AMQPMessage(msgpack_pack($job_results->toArray()), ['correlation_id' => $received_job->getId()])
                    );
                    $connection->getChannel()->basic_ack($message->delivery_info['delivery_tag']);
                    return;
                }

                if(!isset($this->functions[$received_job->getName()]))
                {
                    Log::debug('net.nosial.tamerlib', 'received unknown function: ' . $received_job->getId());
                    $connection->getChannel()->basic_nack($message->delivery_info['delivery_tag'], false, true);
                    return;
                }

                Log::debug('net.nosial.tamerlib', 'received function: ' . $received_job->getId());
                $function = $this->functions[$received_job->getName()];
                $callback = $function['function'];

                try
                {
                    $result = $callback($received_job->getData(), $function['context']);
                }
                catch(Exception $e)
                {
                    unset($e);

                    // Do not requeue the job, it's a closure
                    $connection->getChannel()->basic_nack($message->delivery_info['delivery_tag']);
                    return;
                }

                $job_results = new JobResults($received_job, JobStatus::Success, $result);
                $connection->getChannel->basic_publish(
                    new AMQPMessage(msgpack_pack($job_results->toArray()), ['correlation_id' => $received_job->getId()])
                );
                $connection->getChannel()->basic_ack($message->delivery_info['delivery_tag']);
            };

            $connection->getChannel()->basic_consume('tamer_queue', '', false, false, false, false, $callback);

            if ($blocking)
            {
                while(true)
                {
                    $connection->getChannel()->wait();
                }
            }
            else
            {
                $start = microtime(true);
                while (true)
                {
                    if (microtime(true) - $start >= $timeout / 1000)
                    {
                        break;
                    }

                    $connection->getChannel()->wait();
                }
            }
        }

        /**
         * Disconnects from the server when the object is destroyed
         */
        public function __destruct()
        {
            try
            {
                $this->disconnect();
            }
            catch(Exception $e)
            {
                unset($e);
                // Ignore
            }
        }

    }