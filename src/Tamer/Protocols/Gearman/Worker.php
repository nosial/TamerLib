<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Protocols\Gearman;

    use Exception;
    use GearmanJob;
    use GearmanWorker;
    use LogLib\Log;
    use Opis\Closure\SerializableClosure;
    use Tamer\Abstracts\JobStatus;
    use Tamer\Exceptions\ConnectionException;
    use Tamer\Interfaces\WorkerProtocolInterface;
    use Tamer\Objects\Job;
    use Tamer\Objects\JobResults;

    class Worker implements WorkerProtocolInterface
    {
        /**
         * The Gearman Worker Instance (if connected)
         *
         * @var GearmanWorker|null
         */
        private $worker;

        /**
         * The list of servers that have been added
         *
         * @var array
         */
        private $defined_servers;

        /**
         * Indicates if the worker should automatically reconnect to the server
         *
         * @var bool
         */
        private $automatic_reconnect;

        /**
         * The Unix Timestamp of when the next reconnect should occur (if automatic_reconnect is true)
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
         * Public Constructor with optional username and password
         *
         * @param string|null $username
         * @param string|null $password
         */
        public function __construct(?string $username=null, ?string $password=null)
        {
            $this->worker = null;
            $this->defined_servers = [];
            $this->automatic_reconnect = false;
            $this->next_reconnect = time() + 1800;
            $this->options = [];
        }

        /**
         * Adds a server to the list of servers to use
         *
         * @link http://php.net/manual/en/gearmanworker.addserver.php
         * @param string $host (
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
         * @link http://php.net/manual/en/gearmanworker.addservers.php
         * @param string[] $servers (host:port, host:port, ...)
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
         * Connects to the server
         *
         * @return void
         * @throws ConnectionException
         */
        public function connect(): void
        {
            if($this->isConnected())
                return;

            $this->worker = new GearmanWorker();
            $this->worker->addOptions(GEARMAN_WORKER_GRAB_UNIQ);

            Log::debug('net.nosial.tamerlib', 'connecting to gearman server(s)');

            foreach($this->defined_servers as $host => $ports)
            {
                foreach($ports as $port)
                {
                    try
                    {
                        $this->worker->addServer($host, $port);
                        Log::debug('net.nosial.tamerlib', 'connected to gearman server: ' . $host . ':' . $port);
                    }
                    catch(Exception $e)
                    {
                        throw new ConnectionException('Failed to connect to Gearman server: ' . $host . ':' . $port, 0, $e);
                    }
                }
            }

            $this->worker->addFunction('tamer_closure', function(GearmanJob $job)
            {
                $received_job = Job::fromArray(msgpack_unpack($job->workload()));
                Log::debug('net.nosial.tamerlib', 'received closure: ' . $received_job->getId());

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
                Log::debug('net.nosial.tamerlib', 'completed closure: ' . $received_job->getId());
            });
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

            $this->worker->unregisterAll();
            unset($this->worker);
            $this->worker = null;
        }

        /**
         * Reconnects to the server if the connection has been lost
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
         * Returns true if the worker is connected to the server
         *
         * @return bool
         */
        public function isConnected(): bool
        {
            return $this->worker !== null;
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
         * Sets the options to use when connecting to the server
         *
         * @param array $options
         * @return bool
         * @inheritDoc
         */
        public function setOptions(array $options): void
        {
            $this->options = $options;
        }

        /**
         * Returns the options to use when connecting to the server
         *
         * @return array
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        /**
         * Clears the options to use when connecting to the server
         *
         * @return void
         */
        public function clearOptions(): void
        {
            $this->options = [];
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
         * @return void
         */
        public function enableAutomaticReconnection(bool $enable): void
        {
            $this->automatic_reconnect = $enable;
        }

        /**
         * Adds a function to the list of functions to call
         *
         * @link http://php.net/manual/en/gearmanworker.addfunction.php
         * @param string $name The name of the function to register with the job server
         * @param callable $callable The callback function to call when the job is received
         * @return void
         */
        public function addFunction(string $name, callable $callable): void
        {
            $this->worker->addFunction($name, function(GearmanJob $job) use ($callable)
            {
                $received_job = Job::fromArray(msgpack_unpack($job->workload()));
                Log::debug('net.nosial.tamerlib', 'received job: ' . $received_job->getId());

                try
                {
                    $result = $callable($received_job);
                }
                catch(Exception $e)
                {
                    $job->sendFail();
                    unset($e);
                    return;
                }

                $job_results = new JobResults($received_job, JobStatus::Success, $result);
                $job->sendComplete(msgpack_pack($job_results->toArray()));
                Log::debug('net.nosial.tamerlib', 'completed job: ' . $received_job->getId());
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
         * Waits for a job and calls the appropriate callback function
         *
         * @link http://php.net/manual/en/gearmanworker.work.php
         * @param bool $blocking (default: true) Whether to block until a job is received
         * @param int $timeout (default: 500) The timeout in milliseconds (if $blocking is false)
         * @param bool $throw_errors (default: false) Whether to throw exceptions on errors
         * @return void Returns nothing
         * @throws ConnectionException
         */
        public function work(bool $blocking=true, int $timeout=500, bool $throw_errors=false): void
        {
            $this->worker->setTimeout($timeout);

            while(true)
            {
                @$this->preformAutoreconf();
                @$this->worker->work();

                if($this->worker->returnCode() == GEARMAN_COULD_NOT_CONNECT)
                {
                    throw new ConnectionException('Could not connect to Gearman server');
                }

                if($this->worker->returnCode() == GEARMAN_TIMEOUT && !$blocking)
                {
                    break;
                }

                if($this->worker->returnCode() != GEARMAN_SUCCESS && $throw_errors)
                {
                    Log::error('net.nosial.tamerlib', 'Gearman worker error: ' . $this->worker->error());
                }

                if($blocking)
                {
                    usleep($timeout);
                }
            }
        }

        /**
         * Executes all remaining tasks and closes the connection
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