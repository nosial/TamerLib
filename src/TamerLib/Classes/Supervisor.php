<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Classes;

    use Exception;
    use LogLib\Log;
    use Symfony\Component\Process\Process;
    use TamerLib\Objects\WorkerInstance;

    class Supervisor
    {
        /**
         * A list of all the workers that are initialized
         *
         * @var WorkerInstance[]
         */
        private $workers;

        /**
         * The protocol to pass to the worker instances
         *
         * @var string
         */
        private $protocol;

        /**
         * The list of servers to pass to the worker instances (eg; host:port)
         *
         * @var string[]
         */
        private $servers;

        /**
         * (Optional) The username to pass to the worker instances
         *
         * @var string|null
         */
        private $username;

        /**
         * (Optional) The password to pass to the worker instances
         *
         * @var string|null
         */
        private $password;

        /**
         *
         */
        public function __construct(string $protocol, array $servers, ?string $username = null, ?string $password = null)
        {
            $this->workers = [];
            $this->protocol = $protocol;
            $this->servers = $servers;
            $this->username = $username;
            $this->password = $password;
        }

        /**
         * Adds a worker to the supervisor instance
         *
         * @param string $target
         * @param int $instances
         * @return void
         * @throws Exception
         */
        public function addWorker(string $target, int $instances): void
        {
            for ($i = 0; $i < $instances; $i++)
            {
                $this->workers[] = new WorkerInstance($target, $this->protocol, $this->servers, $this->username, $this->password);
            }
        }

        /**
         * Starts all the workers
         *
         * @return void
         * @throws Exception
         */
        public function start(): void
        {
            /** @var WorkerInstance $worker */
            foreach ($this->workers as $worker)
            {
                $worker->start();
            }

            // Ensure that all the workers are running
            foreach($this->workers as $worker)
            {
                if (!$worker->isRunning())
                {
                    throw new Exception("Worker {$worker->getId()} is not running");
                }

                while(true)
                {
                    switch($worker->getProcess()->getStatus())
                    {
                        case Process::STATUS_STARTED:
                            Log::debug('net.nosial.tamerlib', "worker {$worker->getId()} is running");
                            break 2;

                        case Process::STATUS_TERMINATED:
                            throw new Exception("Worker {$worker->getId()} has terminated");

                        default:
                            echo "Worker {$worker->getId()} is {$worker->getProcess()->getStatus()}" . PHP_EOL;
                    }
                }
            }
        }

        /**
         * Stops all the workers
         *
         * @return void
         * @throws Exception
         */
        public function stop(): void
        {
            /** @var WorkerInstance $worker */
            foreach ($this->workers as $worker)
            {
                $worker->stop();
            }
        }

        /**
         * Restarts all the workers
         *
         * @return void
         * @throws Exception
         */
        public function restart(): void
        {
            /** @var WorkerInstance $worker */
            foreach ($this->workers as $worker)
            {
                $worker->stop();
                $worker->start();
            }
        }

        /**
         * Monitors all the workers and restarts them if they are not running
         *
         * @param bool $blocking
         * @param bool $auto_restart
         * @return void
         * @throws Exception
         */
        public function monitor(bool $blocking=false, bool $auto_restart=true): void
        {
            while(true)
            {
                /** @var WorkerInstance $worker */
                foreach ($this->workers as $worker)
                {
                    if (!$worker->isRunning())
                    {
                        if ($auto_restart)
                        {
                            Log::warning('net.nosial.tamerlib', "worker {$worker->getId()} is not running, restarting");
                            $worker->start();
                        }
                        else
                        {
                            throw new Exception("Worker {$worker->getId()} is not running");
                        }
                    }
                }

                if (!$blocking)
                {
                    break;
                }

                sleep(1);
            }
        }

        /**
         * @throws Exception
         */
        public function __destruct()
        {
            $this->stop();
        }

    }