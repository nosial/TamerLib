<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Protocols\RabbitMq;

    use Exception;
    use PhpAmqpLib\Channel\AMQPChannel;
    use PhpAmqpLib\Connection\AMQPStreamConnection;
    use PhpAmqpLib\Message\AMQPMessage;
    use TamerLib\Abstracts\JobStatus;
    use TamerLib\Interfaces\WorkerProtocolInterface;
    use TamerLib\Objects\Job;
    use TamerLib\Objects\JobResults;

    class Worker implements WorkerProtocolInterface
    {
        /**
         * @var array
         */
        private $server_cache;

        /**
         * @var false
         */
        private $automatic_reconnect;

        /**
         * @var int
         */
        private $next_reconnect;

        /**
         * @var array
         */
        private $functions;

        /**
         * @var string|null
         */
        private $username;

        /**
         * @var string|null
         */
        private $password;

        /**
         * @var AMQPStreamConnection|null
         */
        private $connection;

        /**
         * @var AMQPChannel|null
         */
        private $channel;

        /**
         * @var array
         */
        private $options;


        /**
         * @inheritDoc
         */
        public function __construct(?string $username = null, ?string $password = null)
        {
            $this->server_cache = [];
            $this->functions = [];
            $this->automatic_reconnect = false;
            $this->next_reconnect = time() + 1800;
            $this->username = $username;
            $this->password = $password;

            try
            {
                $this->reconnect();
            }
            catch(Exception $e)
            {
                unset($e);
            }
        }

        /**
         * @inheritDoc
         */
        public function addServer(string $host, int $port): bool
        {
            if(!isset($this->server_cache[$host]))
            {
                $this->server_cache[$host] = [];
            }

            if(in_array($port, $this->server_cache[$host]))
            {
                return true;
            }

            $this->server_cache[$host][] = $port;
            $this->reconnect();

            return true;
        }

        /**
         * @inheritDoc
         */
        public function addServers(array $servers): void
        {
            foreach($servers as $server)
            {
                $server = explode(':', $server);
                $this->addServer($server[0], $server[1]);
            }
        }

        /**
         * @inheritDoc
         */
        public function setOptions(array $options): bool
        {
            $this->options = $options;
            return true;
        }

        /**
         * @inheritDoc
         */
        public function automaticReconnectionEnabled(): bool
        {
            return $this->automatic_reconnect;
        }

        /**
         * @inheritDoc
         */
        public function enableAutomaticReconnection(bool $enable): void
        {
            $this->automatic_reconnect = $enable;
        }

        /**
         * @inheritDoc
         */
        public function addFunction(string $name, callable $callable, mixed $context = null): void
        {
            $this->functions[$name] = [
                'function' => $callable,
                'context' => $context
            ];
        }

        /**
         * @inheritDoc
         */
        public function removeFunction(string $function_name): void
        {
            unset($this->functions[$function_name]);
        }

        /**
         * @inheritDoc
         */
        public function work(bool $blocking = true, int $timeout = 500, bool $throw_errors = false): void
        {
            $callback = function($message) use ($throw_errors)
            {
                var_dump($message->body);
                $job = Job::fromArray(msgpack_unpack($message->body));

                $job_results = new JobResults($job, JobStatus::Success, 'Hello from worker!');

                try
                {
                    // Return $job_results
                    $this->channel->basic_publish(
                        new AMQPMessage(
                            msgpack_pack($job_results->toArray()),
                            [
                                'correlation_id' => $job->getId()
                            ]
                        )
                    );

                    $this->channel->basic_ack($message->delivery_info['delivery_tag']);
                }
                catch (Exception $e)
                {
                    if ($throw_errors)
                    {
                        throw $e;
                    }

                    $job_results = new JobResults($job, JobStatus::Exception, $e->getMessage());

                    // Return $job_results
                    $this->channel->basic_publish(
                        new AMQPMessage(
                            msgpack_pack($job_results->toArray()),
                            [
                                'correlation_id' => $job->getId()
                            ]
                        )
                    );

                    $this->channel->basic_ack($message->delivery_info['delivery_tag']);
                }
            };

            $this->channel->basic_consume('tamer_queue', '', false, false, false, false, $callback);

            if ($blocking)
            {
                while(true)
                {
                    $this->channel->wait();
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

                    $this->channel->wait();
                }
            }
        }

        private function reconnect()
        {
            $connections = [];

            if(count($this->server_cache) === 0)
                return;

            foreach($this->server_cache as $host => $ports)
            {
                foreach($ports as $port)
                {
                    $host = [
                        'host' => $host,
                        'port' => $port
                    ];

                    if($this->username !== null)
                        $host['username'] = $this->username;

                    if($this->password !== null)
                        $host['password'] = $this->password;

                    $connections[] = $host;
                }
            }

            // Can only connect to one server for now, so we'll just use the first one
            $selected_connection = $connections[0];
            $this->disconnect();
            $this->connection = new AMQPStreamConnection(
                $selected_connection['host'],
                $selected_connection['port'],
                $selected_connection['username'] ?? null,
                $selected_connection['password'] ?? null
            );

            $this->channel = $this->connection->channel();
            $this->channel->queue_declare('tamer_queue', false, true, false, false);
        }

        /**
         * Disconnects from the server
         *
         * @return void
         */
        private function disconnect()
        {
            try
            {
                if(!is_null($this->channel))
                {
                    $this->channel->close();
                }
            }
            catch(Exception $e)
            {
                unset($e);
            }
            finally
            {
                $this->channel = null;
            }

            try
            {
                if(!is_null($this->connection))
                {
                    $this->connection->close();
                }
            }
            catch(Exception $e)
            {
                unset($e);
            }
            finally
            {
                $this->connection = null;
            }
        }

        /**
         * Disconnects from the server when the object is destroyed
         */
        public function __destruct()
        {
            $this->disconnect();
        }

    }