<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Protocols\RabbitMq;

    use Closure;
    use Exception;
    use PhpAmqpLib\Channel\AMQPChannel;
    use PhpAmqpLib\Connection\AMQPStreamConnection;
    use PhpAmqpLib\Message\AMQPMessage;
    use TamerLib\Abstracts\TaskPriority;
    use TamerLib\Exceptions\ServerException;
    use TamerLib\Interfaces\ClientProtocolInterface;
    use TamerLib\Objects\Job;
    use TamerLib\Objects\JobResults;
    use TamerLib\Objects\Task;

    class Client implements ClientProtocolInterface
    {
        /**
         * @var array
         */
        private $server_cache;

        /**
         * Used for tracking the current execution of tasks and run callbacks on completion
         *
         * @var Task[]
         */
        private $tasks;

        /**
         * @var bool
         */
        private $automatic_reconnect;

        /**
         * @var int
         */
        private $next_reconnect;

        /**
         * @var string|null
         */
        private $username;

        /**
         * @var string|null
         */
        private $password;

        /**
         * @var array
         */
        private $options;

        /**
         * @var AMQPStreamConnection|null
         */
        private $connection;

        /**
         * @var AMQPChannel|null
         */
        private $channel;

        /***
         * @param string|null $username
         * @param string|null $password
         */
        public function __construct(?string $username=null, ?string $password=null)
        {
            $this->tasks = [];
            $this->automatic_reconnect = false;
            $this->next_reconnect = time() + 1800;
            $this->server_cache = [];
            $this->options = [];
            $this->connection = null;
            $this->username = $username;
            $this->password = $password;

            try
            {
                $this->reconnect();
            }
            catch(ServerException $e)
            {
                unset($e);
            }
        }

        public function setOptions(array $options): bool
        {
            $this->options = $options;
            return true;
        }

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
         * Adds a list of servers to the list of servers to use
         *
         * @param array $servers (host:port, host:port, ...)
         * @return bool
         */
        public function addServers(array $servers): bool
        {
            foreach($servers as $server)
            {
                $server = explode(':', $server);
                $this->addServer($server[0], $server[1]);
            }

            return true;
        }

        /**
         * Calculates the priority for a task based on the priority level
         *
         * @param int $priority
         * @return int
         */
        private static function calculatePriority(int $priority): int
        {
            if($priority < TaskPriority::Low)
                return 0;

            if($priority > TaskPriority::High)
                return 255;

            return (int) round(($priority / TaskPriority::High) * 255);
        }

        /**
         * @param Task $task
         * @return void
         */
        public function do(Task $task): void
        {
            $job = new Job($task);

            $message = new AMQPMessage(msgpack_pack($job->toArray()), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => self::calculatePriority($task->getPriority()),
            ]);

            $this->channel->basic_publish($message, '', 'tamer_queue');
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
         * @param $callback
         * @return void
         */
        public function queueClosure(Closure $closure, $callback): void
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

            $correlationIds = [];

            /** @var Task $task */
            foreach($this->tasks as $task)
            {
                $correlationIds[] = $task->getId();

                $job = new Job($task);

                $message = new AMQPMessage(msgpack_pack($job->toArray()), [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'correlation_id' => $task->getId(),
                    'reply_to' => 'tamer_queue',
                    'priority' => self::calculatePriority($task->getPriority()),
                ]);

                $this->channel->basic_publish($message, '', 'tamer_queue');
            }

            // Register callback for each task
            $callback = function($msg) use (&$correlationIds)
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
                if ($index !== false) {
                    unset($correlationIds[$index]);
                }

                $this->channel->basic_ack($msg->delivery_info['delivery_tag']);

                // Stop consuming when all tasks are processed
                if(count($correlationIds) === 0)
                {
                    $this->channel->basic_cancel($msg->delivery_info['consumer_tag']);
                }
            };

            $this->channel->basic_consume('tamer_queue', '', false, false, false, false, $callback);

            // Start consuming messages
            while(count($this->channel->callbacks))
            {
                $this->channel->wait();
            }

            return true;
        }

        /**
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