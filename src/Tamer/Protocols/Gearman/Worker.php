<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Protocols\Gearman;

    use Exception;
    use GearmanJob;
    use Opis\Closure\SerializableClosure;
    use Tamer\Abstracts\JobStatus;
    use Tamer\Exceptions\ServerException;
    use Tamer\Exceptions\WorkerException;
    use Tamer\Interfaces\WorkerProtocolInterface;
    use Tamer\Objects\Job;
    use Tamer\Objects\JobResults;

    class Worker implements WorkerProtocolInterface
    {
        /**
         * @var \GearmanWorker|null
         */
        private $worker;

        /**
         * @var array
         */
        private $server_cache;

        /**
         * @var bool
         */
        private $automatic_reconnect;

        /**
         * @var int
         */
        private $next_reconnect;

        public function __construct(?string $username=null, ?string $password=null)
        {
            $this->worker = null;
            $this->server_cache = [];
            $this->automatic_reconnect = false;
            $this->next_reconnect = time() + 1800;

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
         * @param array $options
         * @return bool
         * @inheritDoc
         */
        public function addOptions(array $options): bool
        {
            if($this->worker == null)
            {
                return false;
            }

            $options = array_map(function($option)
            {
                return constant($option);
            }, $options);

            return $this->worker->addOptions(array_sum($options));
        }
        /**
         * Adds a server to the list of servers to use
         *
         * @link http://php.net/manual/en/gearmanworker.addserver.php
         * @param string $host (
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
                return $this->worker->addServer($host, $port);
            }
            catch(Exception $e)
            {
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
            }
        }

        /**
         * Adds a list of servers to the list of servers to use
         *
         * @link http://php.net/manual/en/gearmanworker.addservers.php
         * @param string[] $servers (host:port, host:port, ...)
         * @return void
         * @throws ServerException
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
         * Adds a function to the list of functions to call
         *
         * @link http://php.net/manual/en/gearmanworker.addfunction.php
         * @param string $function_name The name of the function to register with the job server
         * @param callable $function The callback function to call when the job is received
         * @param mixed|null $context (optional) The context to pass to the callback function
         * @return void
         */
        public function addFunction(string $function_name, callable $function, mixed $context=null): void
        {
            $this->worker->addFunction($function_name, function(GearmanJob $job) use ($function, $context)
            {
                $received_job = Job::fromArray(msgpack_unpack($job->workload()));

                try
                {
                    $result = $function($received_job, $context);
                }
                catch(Exception $e)
                {
                    $job->sendFail();
                    return;
                }

                $job_results = new JobResults($received_job, JobStatus::Success, $result);
                $job->sendComplete(msgpack_pack($job_results->toArray()));

            });
        }

        /**
         * Removes a function from the list of functions to call
         *
         * @param string $function_name The name of the function to unregister
         * @return void
         */
        public function removeFunction(string $function_name): void
        {
            $this->worker->unregister($function_name);
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
         * @return void
         */
        public function setAutomaticReconnect(bool $automatic_reconnect): void
        {
            $this->automatic_reconnect = $automatic_reconnect;
        }

        /**
         * @throws ServerException
         */
        private function reconnect()
        {
            $this->worker = new \GearmanWorker();
            $this->worker->addOptions(GEARMAN_WORKER_GRAB_UNIQ);

            foreach($this->server_cache as $host => $ports)
            {
                foreach($ports as $port)
                {
                    $this->addServer($host, $port);
                }
            }

            $this->worker->addFunction('tamer_closure', function(GearmanJob $job)
            {
                $received_job = Job::fromArray(msgpack_unpack($job->workload()));

                try
                {
                    /** @var SerializableClosure $closure */
                    $closure = $received_job->getData();
                    $result = $closure($received_job);
                }
                catch(Exception $e)
                {
                    $job->sendFail();
                    unset($e);
                    return;
                }

                $job_results = new JobResults($received_job, JobStatus::Success, $result);
                $job->sendComplete(msgpack_pack($job_results->toArray()));
            });
        }

        /**
         * Waits for a job and calls the appropriate callback function
         *
         * @link http://php.net/manual/en/gearmanworker.work.php
         * @param bool $blocking (default: true) Whether to block until a job is received
         * @param int $timeout (default: 500) The timeout in milliseconds (if $blocking is false)
         * @param bool $throw_errors (default: false) Whether to throw exceptions on errors
         * @return void Returns nothing
         * @throws ServerException If the worker cannot connect to the server
         * @throws WorkerException If the worker encounters an error while working if $throw_errors is true
         */
        public function work(bool $blocking=true, int $timeout=500, bool $throw_errors=false): void
        {
            if($this->automatic_reconnect && (time() > $this->next_reconnect))
            {
                $this->reconnect();
                $this->next_reconnect = time() + 1800;
            }

            $this->worker->setTimeout($timeout);

            while(true)
            {
                @$this->worker->work();

                if($this->worker->returnCode() == GEARMAN_COULD_NOT_CONNECT)
                {
                    throw new ServerException('Could not connect to Gearman server');
                }

                if($this->worker->returnCode() == GEARMAN_TIMEOUT && !$blocking)
                {
                    break;
                }

                if($this->worker->returnCode() != GEARMAN_SUCCESS && $throw_errors)
                {
                    throw new WorkerException('Gearman worker error: ' . $this->worker->error(), $this->worker->returnCode());
                }
            }
        }
    }