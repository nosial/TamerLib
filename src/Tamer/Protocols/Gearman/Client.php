<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Protocols\Gearman;

    use Closure;
    use Exception;
    use GearmanTask;
    use LogLib\Log;
    use Tamer\Abstracts\JobStatus;
    use Tamer\Abstracts\TaskPriority;
    use Tamer\Exceptions\ServerException;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Objects\Job;
    use Tamer\Objects\JobResults;
    use Tamer\Objects\Task;

    class Client implements ClientProtocolInterface
    {
        /**
         * @var \GearmanClient|null $client
         */
        private $client;

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
            $this->server_cache = [];

            try
            {
                $this->reconnect();
            }
            catch(ServerException $e)
            {
                unset($e);
            }
        }

        /**
         * Adds client options
         *
         * @link http://php.net/manual/en/gearmanclient.addoptions.php
         * @param int[] $options (GEARMAN_CLIENT_NON_BLOCKING, GEARMAN_CLIENT_UNBUFFERED_RESULT, GEARMAN_CLIENT_FREE_TASKS)
         * @return bool
         */
        public function addOptions(array $options): bool
        {
            // Parse $options combination via bitwise OR operator
            $options = array_reduce($options, function($carry, $item)
            {
                return $carry | $item;
            });

            return $this->client->addOptions($options);
        }

        /**
         * Registers callbacks for the client
         *
         * @return void
         */
        private function registerCallbacks(): void
        {
            $this->client->setCompleteCallback([$this, 'callbackHandler']);
            $this->client->setFailCallback([$this, 'callbackHandler']);
            $this->client->setDataCallback([$this, 'callbackHandler']);
            $this->client->setStatusCallback([$this, 'callbackHandler']);
        }


        /**
         * Adds a server to the list of servers to use
         *
         * @link http://php.net/manual/en/gearmanclient.addserver.php
         * @param string $host (127.0.0.1)
         * @param int $port (default: 4730)
         * @return bool
         * @throws ServerException
         */
        public function addServer(string $host='127.0.0.1', int $port=4730): bool
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

            try
            {
                return $this->client->addServer($host, $port);
            }
            catch(Exception $e)
            {
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
            }
        }

        /**
         * Adds a list of servers to the list of servers to use
         *
         * @link http://php.net/manual/en/gearmanclient.addservers.php
         * @param array $servers (host:port, host:port, ...)
         * @return bool
         */
        public function addServers(array $servers): bool
        {
            return $this->client->addServers(implode(',', $servers));
        }

        /**
         * Executes a closure in the background
         *
         * @param Closure $closure
         * @return void
         * @throws ServerException
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
         * @throws ServerException
         */
        public function do(Task $task): void
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

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
         * @throws ServerException
         */
        public function queue(Task $task): void
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

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
         * @param $callback
         * @return void
         * @throws ServerException
         */
        public function queueClosure(Closure $closure, $callback): void
        {
            $closure_task = new Task('tamer_closure', $closure, $callback);
            $closure_task->setClosure(true);
            $this->queue($closure_task);
        }

        /**
         * @return bool
         * @throws ServerException
         */
        public function run(): bool
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

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
            $job_status = match ($task->returnCode())
            {
                GEARMAN_WORK_EXCEPTION => JobStatus::Exception,
                GEARMAN_WORK_FAIL => JobStatus::Failure,
                default => JobStatus::Success,
            };

            try
            {
                Log::debug('net.nosial.tamer', 'callback for task ' . $internal_task->getId() . ' with status ' . $job_status . ' and data size ' . strlen($task->data()) . ' bytes');
                $internal_task->runCallback($job_result);
            }
            catch(Exception $e)
            {
                Log::error('net.nosial.tamer', 'Callback for task ' . $internal_task->getId() . ' failed with error: ' . $e->getMessage(), $e);
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
         * @throws ServerException
         */
        private function reconnect()
        {
            $this->client = new \GearmanClient();

            foreach($this->server_cache as $host => $ports)
            {
                foreach($ports as $port)
                {
                    $this->addServer($host, $port);
                }
            }

            $this->registerCallbacks();
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
        public function isAutomaticReconnect(): bool
        {
            return $this->automatic_reconnect;
        }

        /**
         * @param bool $automatic_reconnect
         */
        public function setAutomaticReconnect(bool $automatic_reconnect): void
        {
            $this->automatic_reconnect = $automatic_reconnect;
        }

        /**
         * Executes all remaining tasks and closes the connection
         */
        public function __destruct()
        {
            try
            {
                $this->client->runTasks();
            }
            catch(Exception $e)
            {
                unset($e);
            }

            unset($this->client);
        }
    }