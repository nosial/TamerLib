<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Protocols\RabbitMq;

    use Closure;
    use Exception;
    use PhpAmqpLib\Message\AMQPMessage;
    use TamerLib\Classes\Functions;
    use TamerLib\Exceptions\ConnectionException;
    use TamerLib\Interfaces\ClientProtocolInterface;
    use TamerLib\Objects\Job;
    use TamerLib\Objects\JobResults;
    use TamerLib\Objects\Task;

    class Client implements ClientProtocolInterface
    {
        /**
         * An array of servers to use
         *
         * @var array
         */
        private $defined_servers;

        /**
         * Used for tracking the current execution of tasks and run callbacks on completion
         *
         * @var Task[]
         */
        private $tasks;

        /**
         * Whether to automatically reconnect to the server if the connection is lost
         *
         * @var bool
         */
        private $automatic_reconnect;

        /**
         * (Optional) The username to use when connecting to the server
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
         * (Optional) An array of options to use when connecting to the server
         *
         * @var array
         */
        private $options;

        /**
         * An array of connections to use
         *
         * @var Connection[]
         */
        private $connections;

        /**
         * Public Constructor
         *
         * @param string|null $username
         * @param string|null $password
         */
        public function __construct(?string $username=null, ?string $password=null)
        {
            $this->tasks = [];
            $this->automatic_reconnect = false;
            $this->defined_servers = [];
            $this->options = [];
            $this->username = $username;
            $this->password = $password;
            $this->connections = [];
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
         * Adds a list of servers to the list of servers to use
         *
         * @param array $servers (host:port, host:port, ...)
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
         * Connects to the server(s) defined
         *
         * @return void
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
         * Reconnects to the server
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
         * Sets the options array
         *
         * @param array $options
         * @return void
         */
        public function setOptions(array $options): void
        {
            $this->options = $options;
        }

        /**
         * Returns the options array
         *
         * @return array
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        /**
         * Clears the options array
         *
         * @return void
         */
        public function clearOptions(): void
        {
            $this->options = [];
        }

        /**
         * Returns True if the client is automatically reconnecting to the server
         *
         * @return bool
         */
        public function automaticReconnectionEnabled(): bool
        {
            return $this->automatic_reconnect;
        }

        /**
         * Enables or disables automatic reconnecting to the server
         *
         * @param bool $enable
         * @return void
         */
        public function enableAutomaticReconnection(bool $enable): void
        {
            $this->automatic_reconnect = $enable;
        }

        /**
         * Runs a task in the background (Fire and Forget)
         *
         * @param Task $task
         * @return void
         */
        public function do(Task $task): void
        {
            if(!$this->isConnected())
                return;

            $job = new Job($task);
            $message = new AMQPMessage(msgpack_pack($job->toArray()), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'correlation_id' => $task->getId(),
                'priority' => Functions::calculatePriority($task->getPriority()),
            ]);

            // Select random connection
            $connection =  $this->connections[array_rand($this->connections)];
            if($this->automatic_reconnect)
                $connection->preformAutoreconf();
            $connection->getChannel()->basic_publish($message, '', 'tamer_queue');
        }

        /**
         * Executes a closure in the background
         *
         * @param Closure $closure
         * @return void
         */
        public function doClosure(Closure $closure): void
        {
            $closure_task = new Task('tamer_closure', $closure);
            $closure_task->setClosure(true);
            $this->do($closure_task);
        }

        /**
         * Queues a task to be executed
         *
         * @param Task $task
         * @return void
         */
        public function queue(Task $task): void
        {
            $this->tasks[] = $task;
        }

        /**
         * Adds a closure task to the list of tasks to run
         *
         * @param Closure $closure
         * @param Closure|null $callback
         * @return void
         */
        public function queueClosure(Closure $closure, ?Closure $callback=null): void
        {
            $closure_task = new Task('tamer_closure', $closure, $callback);
            $closure_task->setClosure(true);
            $this->queue($closure_task);
        }

        /**
         * Executes all the tasks that has been added
         *
         * @return bool
         */
        public function run(): bool
        {
            if(count($this->tasks) === 0)
                return false;

            if(!$this->isConnected())
                return false;

            $this->preformAutoreconf();
            $correlationIds = [];
            $connection =  $this->connections[array_rand($this->connections)];
            if($this->automatic_reconnect)
                $connection->preformAutoreconf();

            /** @var Task $task */
            foreach($this->tasks as $task)
            {
                $correlationIds[] = $task->getId();
                $job = new Job($task);

                $message = new AMQPMessage(msgpack_pack($job->toArray()), [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'correlation_id' => $task->getId(),
                    'reply_to' => 'tamer_queue',
                    'priority' => Functions::calculatePriority($task->getPriority()),
                ]);

                $connection->getChannel()->basic_publish($message, '', 'tamer_queue');
            }

            // Register callback for each task
            $callback = function($msg) use (&$correlationIds, $connection)
            {
                $job_result = JobResults::fromArray(msgpack_unpack($msg->body));
                $task = $this->getTaskById($job_result->getId());

                try
                {
                    $task->runCallback($job_result);
                }
                catch(Exception $e)
                {
                    echo $e->getMessage();
                }

                // Remove the processed correlation_id
                $index = array_search($msg->get('correlation_id'), $correlationIds);

                if ($index !== false)
                {
                    unset($correlationIds[$index]);
                    $connection->getChannel()->basic_ack($msg->delivery_info['delivery_tag']);
                }
                else
                {
                    $connection->getChannel()->basic_nack($msg->delivery_info['delivery_tag'], false, true);
                }

                // Stop consuming when all tasks are processed
                if(count($correlationIds) === 0)
                {
                    $connection->getChannel()->basic_cancel($msg->delivery_info['consumer_tag']);
                }
            };

            $connection->getChannel()->basic_consume('tamer_queue', '', false, false, false, false, $callback);

            // Start consuming messages
            while(count($connection->getChannel()->callbacks))
            {
                $connection->getChannel()->wait();
            }

            return true;
        }

        /**
         * Returns a task by its id
         *
         * @param string $id
         * @return Task|null
         */
        private function getTaskById(string $id): ?Task
        {
            foreach($this->tasks as $task)
            {
                if($task->getId() === $id)
                {
                    return $task;
                }
            }

            return null;
        }

        /**
         * The automatic reconnect process
         *
         * @return void
         */
        private function preformAutoreconf(): void
        {
            if($this->automatic_reconnect)
            {
                foreach($this->connections as $connection)
                {
                    $connection->preformAutoreconf();
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
            }
        }

    }