<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Protocols;

    use Exception;
    use GearmanTask;
    use LogLib\Log;
    use Tamer\Abstracts\JobStatus;
    use Tamer\Abstracts\TaskPriority;
    use Tamer\Exceptions\ServerException;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Objects\JobResults;
    use Tamer\Objects\Task;

    class GearmanClient implements ClientProtocolInterface
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
         */
        public function __construct()
        {
            $this->client = null;
            $this->tasks = [];
            $this->automatic_reconnect = false;
            $this->next_reconnect = time() + 1800;

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
         * Processes a task in the background
         *
         * @param Task $task
         * @return bool
         * @throws ServerException
         */
        public function doBackground(Task $task): bool
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

            $this->tasks[] = $task;

            switch($task->getPriority())
            {
                case TaskPriority::High:
                    return $this->client->doHighBackground($task->getFunctionName(), $task->getData(), $task->getId());

                case TaskPriority::Low:
                    return $this->client->doLowBackground($task->getFunctionName(), $task->getData(), $task->getId());

                default:
                case TaskPriority::Normal:
                    return $this->client->doBackground($task->getFunctionName(), $task->getData(), $task->getId());
            }
        }

        /**
         * Processes a task in the foreground
         *
         * @param Task $task
         * @return JobResults
         * @throws ServerException
         */
        public function do(Task $task): JobResults
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

            $this->tasks[] = $task;

            switch($task->getPriority())
            {
                case TaskPriority::High:
                    return new JobResults($task, JobStatus::Success, $this->client->doHigh($task->getFunctionName(), $task->getData(), $task->getId()));

                case TaskPriority::Low:
                    return new JobResults($task, JobStatus::Success, $this->client->doLow($task->getFunctionName(), $task->getData(), $task->getId()));

                default:
                case TaskPriority::Normal:
                    return new JobResults($task, JobStatus::Success, $this->client->doNormal($task->getFunctionName(), $task->getData(), $task->getId()));
            }
        }

        public function addTask(Task $task): ClientProtocolInterface
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

            $this->tasks[] = $task;

            switch($task->getPriority())
            {
                case TaskPriority::High:
                    $this->client->addTaskHigh($task->getFunctionName(), $task->getData(), $task->getId());
                    break;

                case TaskPriority::Low:
                    $this->client->addTaskLow($task->getFunctionName(), $task->getData(), $task->getId());
                    break;

                default:
                case TaskPriority::Normal:
                    $this->client->addTask($task->getFunctionName(), $task->getData(), $task->getId());
                    break;
            }

            return $this;
        }


        public function addBackgroundTask(Task $task): ClientProtocolInterface
        {
            if($this->automatic_reconnect && time() > $this->next_reconnect)
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

            $this->tasks[] = $task;

            switch($task->getPriority())
            {
                case TaskPriority::High:
                    $this->client->addTaskHighBackground($task->getFunctionName(), $task->getData(), $task->getId());
                    break;

                case TaskPriority::Low:
                    $this->client->addTaskLowBackground($task->getFunctionName(), $task->getData(), $task->getId());
                    break;

                default:
                case TaskPriority::Normal:
                    $this->client->addTaskBackground($task->getFunctionName(), $task->getData(), $task->getId());
                    break;
            }

            return $this;
        }

        /**
         * @return bool
         * @throws ServerException
         */
        public function doTasks(): bool
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
            $internal_task = $this->getTaskById($task->unique());
            $job_status = match ($task->returnCode())
            {
                GEARMAN_WORK_EXCEPTION => JobStatus::Exception,
                GEARMAN_WORK_FAIL => JobStatus::Failure,
                default => JobStatus::Success,
            };

            $job_results = new JobResults($internal_task, $job_status, ($task->data() ?? null));

            try
            {
                Log::debug('net.nosial.tamer', 'callback for task ' . $internal_task->getId() . ' with status ' . $job_status . ' and data size ' . strlen($task->data()) . ' bytes');
                $internal_task->runCallback($job_results);
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
            var_dump($this->tasks);
            var_dump($id);
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
         * @return ClientProtocolInterface
         */
        private function removeTask(Task $task): ClientProtocolInterface
        {
            $this->tasks = array_filter($this->tasks, function($item) use ($task)
            {
                return $item->getId() !== $task->getId();
            });

            return $this;
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
    }