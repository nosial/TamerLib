<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Objects;

    use Exception;
    use LogLib\Log;
    use Symfony\Component\Process\Process;
    use TamerLib\Classes\Functions;

    class WorkerInstance
    {
        /**
         * The worker's instance id
         *
         * @var string
         */
        private $id;

        /**
         * The protocol to use when connecting to the server
         *
         * @var string
         */
        private $protocol;

        /**
         * The servers to connect to
         *
         * @var array
         */
        private $servers;

        /**
         * The username to use when connecting to the server (if applicable)
         *
         * @var string|null
         */
        private $username;

        /**
         * The password to use when connecting to the server (if applicable)
         *
         * @var string|null
         */
        private $password;

        /**
         * The process that is running the worker instance
         *
         * @var Process|null
         */
        private $process;

        /**
         * The target to run the worker instance on (e.g. a file path)
         *
         * @var string
         */
        private $target;

        /**
         * Public Constructor
         *
         * @param string $target
         * @param string $protocol
         * @param array $servers
         * @param string|null $username
         * @param string|null $password
         * @throws Exception
         */
        public function __construct(string $target, string $protocol, array $servers, ?string $username = null, ?string $password = null)
        {
            $this->id = uniqid();
            $this->target = $target;
            $this->protocol = $protocol;
            $this->servers = $servers;
            $this->username = $username;
            $this->password = $password;
            $this->process = null;

            if($target !== 'closure' && file_exists($target) === false)
            {
                throw new Exception('The target file does not exist');
            }
        }

        /**
         * Returns the worker instance id
         *
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * Executes the worker instance in a separate process
         *
         * @return void
         * @throws Exception
         */
        public function start(): void
        {
            $target = $this->target;
            if($target == 'closure')
            {
                $target = __DIR__ . DIRECTORY_SEPARATOR . 'closure';
            }

            $argv = $_SERVER['argv'];
            array_shift($argv);

            $this->process = new Process(array_merge([Functions::findPhpBin(), $target], $argv));
            $this->process->setEnv([
                'TAMER_ENABLED' => 'true',
                'TAMER_PROTOCOL' => $this->protocol,
                'TAMER_SERVERS' => implode(',', $this->servers),
                'TAMER_USERNAME' => $this->username,
                'TAMER_PASSWORD' => $this->password,
                'TAMER_INSTANCE_ID' => $this->id
            ]);


            Log::debug('net.nosial.tamerlib', sprintf('starting worker %s', $this->id));

            // Callback for process output
            $this->process->start(function ($type, $buffer)
            {
                // Add newline if it's missing
                if(substr($buffer, -1) !== PHP_EOL)
                {
                    $buffer .= PHP_EOL;
                }

                print($buffer);
            });
        }

        /**
         * Stops the worker instance
         *
         * @return void
         */
        public function stop(): void
        {
            if($this->process !== null)
            {
                Log::debug('net.nosial.tamerlib', sprintf('Stopping worker %s', $this->id));
                $this->process->stop();
            }
        }

        /**
         * Returns whether the worker instance is running
         *
         * @return bool
         */
        public function isRunning(): bool
        {
            if($this->process !== null)
            {
                return $this->process->isRunning();
            }

            return false;
        }

        /**
         * @return Process|null
         */
        public function getProcess(): ?Process
        {
            return $this->process;
        }

        /**
         * Destructor
         */
        public function __destruct()
        {
            $this->stop();
        }
    }