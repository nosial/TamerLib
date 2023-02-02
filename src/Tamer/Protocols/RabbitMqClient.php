<?php

    namespace Tamer\Protocols;

    use Tamer\Exceptions\ServerException;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Objects\Task;

    class RabbitMqClient implements ClientProtocolInterface
    {
        /**
         * @var \R|null $client
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

        public function addOptions(array $options): bool
        {
            // TODO: Implement addOptions() method.
        }

        public function addServer(string $host, int $port): bool
        {
            // TODO: Implement addServer() method.
        }

        public function addServers(array $servers): bool
        {
            // TODO: Implement addServers() method.
        }

        public function doBackground(Task $task): void
        {
            // TODO: Implement doBackground() method.
        }

        public function addTask(Task $task): void
        {
            // TODO: Implement addTask() method.
        }

        public function run(): bool
        {
            // TODO: Implement run() method.
        }

        public function isAutomaticReconnect(): bool
        {
            // TODO: Implement isAutomaticReconnect() method.
        }

        public function setAutomaticReconnect(bool $automatic_reconnect): void
        {
            // TODO: Implement setAutomaticReconnect() method.
        }
    }