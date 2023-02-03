<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Protocols\Gearman;

    use Closure;
    use Exception;
    use GearmanClient;
    use GearmanTask;
    use LogLib\Log;
    use ncc\Utilities\Console;
    use Tamer\Abstracts\TaskPriority;
    use Tamer\Exceptions\ConnectionException;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Objects\Job;
    use Tamer\Objects\JobResults;
    use Tamer\Objects\Task;

    class Client implements ClientProtocolInterface
    {
        /**
         * The Gearman Client object
         *
         * @var GearmanClient|null $client
         */
        private $client;

        /**
         * An array of servers that have been defined
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
         * Indicates if the client should automatically reconnect to the server if the connection is lost
         * (default: true)
         *
         * @var bool
         */
        private $automatic_reconnect;

        /**
         * The Unix timestamp of the next time the client should attempt to reconnect to the server
         *
         * @var int
         */
        private $next_reconnect;

        /**
         * The options to use when connecting to the server
         *
         * @var array
         */
        private $options;

        /**
         * @inheritDoc
         * @param string|null $username
         * @param string|null $password
         */
        public function __construct(?string $username=null, ?string $password=null)
        {
            $this->client = null;
            $this->tasks = [];
            $this->automatic_reconnect = false;
            $this->next_reconnect = time() + 1800;
            $this->defined_servers = [];
            $this->options = [];
        }


        /**
         * Adds a server to the list of servers to use
         *
         * @link http://php.net/manual/en/gearmanclient.addserver.php
         * @param string $host (127.0.0.1)
         * @param int $port (default: 4730)
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
         * @link http://php.net/manual/en/gearmanclient.addservers.php
         * @param array $servers (host:port, host:port, ...)
         * @return void
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
         * Connects to the server(s)
         *
         * @return void
         * @throws ConnectionException
         */
        public function connect(): void
        {
            if($this->isConnected())
                return;

            $this->client = new GearmanClient();
            
            // Parse $options combination via bitwise OR operator
            $options = array_reduce($this->options, function($carry, $item)
            {
                return $carry | $item;
            });
            
            $this->client->addOptions($options);

            foreach($this->defined_servers as $host => $ports)
            {
                foreach($ports as $port)
                {
                    try
                    {
                        $this->client->addServer($host, $port);
                        Log::debug('net.nosial.tamerlib', 'connected to gearman server: ' . $host . ':' . $port);
                    }
                    catch(Exception $e)
                    {
                        throw new ConnectionException('Failed to connect to Gearman server: ' . $host . ':' . $port, 0, $e);
                    }
                }
            }

            $this->client->setCompleteCallback([$this, 'callbackHandler']);
            $this->client->setFailCallback([$this, 'callbackHandler']);
            $this->client->setDataCallback([$this, 'callbackHandler']);
            $this->client->setStatusCallback([$this, 'callbackHandler']);
        }

        /**
         * Disconnects from the server(s)
         *
         * @return void
         */
        public function disconnect(): void
        {
            if(!$this->isConnected())
                return;

            Log::debug('net.nosial.tamerlib', 'disconnecting from gearman server(s)');
            $this->client->clearCallbacks();
            unset($this->client);
            $this->client = null;
        }

        /**
         * Reconnects to the server(s)
         *
         * @return void
         * @throws ConnectionException
         */
        public function reconnect(): void
        {
            Console::outDebug('net.nosial.tamerlib', 'reconnecting to gearman server(s)');

            $this->disconnect();
            $this->connect();
        }

        /**
         * Returns the current status of the client
         *
         * @inheritDoc
         * @return bool
         */
        public function isConnected(): bool
        {
            if($this->client === null)
            {
                return false;
            }

            return true;
        }

        /**
         * The automatic reconnect process
         *
         * @return void
         */
        private function preformAutoreconf(): void
        {
            if($this->automatic_reconnect && $this->next_reconnect < time())
            {
                try
                {
                    $this->reconnect();
                }
                catch (Exception $e)
                {
                    Log::error('net.nosial.tamerlib', 'Failed to reconnect to Gearman server: ' . $e->getMessage());
                }
                finally
                {
                    $this->next_reconnect = time() + 1800;
                }
            }
        }

        /**
         * Adds client options
         *
         * @link http://php.net/manual/en/gearmanclient.addoptions.php
         * @param int[] $options (GEARMAN_CLIENT_NON_BLOCKING, GEARMAN_CLIENT_UNBUFFERED_RESULT, GEARMAN_CLIENT_FREE_TASKS)
         * @return void
         */
        public function setOptions(array $options): void
        {
            $this->options = $options;
        }

        /**
         * Returns the current client options
         * 
         * @return array
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        /**
         * Clears the current client options
         * 
         * @return void
         */
        public function clearOptions(): void
        {
            $this->options = [];
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
         * Processes a task in the background
         *
         * @param Task $task
         * @return void
         */
        public function do(Task $task): void
        {
            $this->preformAutoreconf();

            $this->tasks[] = $task;
            $job = new Job($task);

            switch($task->getPriority())
            {
                case TaskPriority::High:
                    $this->client->doHighBackground($task->getFunctionName(),  msgpack_pack($job->toArray()));
                    break;

                case TaskPriority::Low:
                    $this->client->doLowBackground($task->getFunctionName(), msgpack_pack($job->toArray()));
                    break;

                default:
                case TaskPriority::Normal:
                    $this->client->doBackground($task->getFunctionName(), msgpack_pack($job->toArray()));
                    break;
            }
        }

        /**
         * Adds a task to the list of tasks to run
         *
         * @param Task $task
         * @return void
         */
        public function queue(Task $task): void
        {
            $this->preformAutoreconf();

            $this->tasks[] = $task;
            $job = new Job($task);

            switch($task->getPriority())
            {
                case TaskPriority::High:
                    $this->client->addTaskHigh($task->getFunctionName(), msgpack_pack($job->toArray()));
                    break;

                case TaskPriority::Low:
                    $this->client->addTaskLow($task->getFunctionName(), msgpack_pack($job->toArray()));
                    break;

                default:
                case TaskPriority::Normal:
                    $this->client->addTask($task->getFunctionName(), msgpack_pack($job->toArray()));
                    break;
            }
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
         * @return bool
         */
        public function run(): bool
        {
            if(!$this->isConnected())
                return false;

            $this->preformAutoreconf();

            if(!$this->client->runTasks())
            {
                return false;
            }

            return true;
        }

        /**
         * Processes a task callback in the foreground
         *
         * @param GearmanTask $task
         * @return void
         */
        public function callbackHandler(GearmanTask $task): void
        {
            $job_result = JobResults::fromArray(msgpack_unpack($task->data()));
            $internal_task = $this->getTaskById($job_result->getId());

            Log::debug('net.nosial.tamerlib', 'callback for task ' . $internal_task->getId() . ' with status ' . $job_result->getStatus() . ' and data size ' . strlen($task->data()) . ' bytes');

            try
            {
                if($internal_task->isClosure())
                {
                    // If the task is a closure, we need to run the callback with the closure's return value
                    // instead of the job result object
                    $internal_task->runCallback($job_result->getData());
                    return;
                }

                $internal_task->runCallback($job_result);
            }
            catch(Exception $e)
            {
                Log::error('net.nosial.tamerlib', 'Failed to run callback for task ' . $internal_task->getId() . ': ' . $e->getMessage(), $e);
            }
            finally
            {
                $this->removeTask($internal_task);
            }
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
         * Removes a task from the list of tasks
         *
         * @param Task $task
         * @return void
         */
        private function removeTask(Task $task): void
        {
            $this->tasks = array_filter($this->tasks, function($item) use ($task)
            {
                return $item->getId() !== $task->getId();
            });

        }

        /**
         * @return bool
         */
        public function automaticReconnectionEnabled(): bool
        {
            return $this->automatic_reconnect;
        }

        /**
         * @param bool $enable
         */
        public function enableAutomaticReconnection(bool $enable): void
        {
            $this->automatic_reconnect = $enable;
        }

        /**
         * Executes all remaining tasks and closes the connection
         */
        public function __destruct()
        {
            try
            {
                $this->run();
            }
            catch(Exception $e)
            {
                unset($e);
            }
            finally
            {
                $this->disconnect();
            }
        }
    }