<?php

    namespace Tamer\Interfaces;

    use Tamer\Objects\Task;

    interface ClientProtocolInterface
    {
        /**
         * Adds options to the client (client specific)
         *
         * @param array $options
         * @return bool
         */
        public function addOptions(array $options): bool;

        /**
         * Adds a server to the list of servers to use
         *
         * @param string $host The host to connect to (eg; 127.0.0.1)
         * @param int $port The port to connect to (eg; 4730)
         * @return bool
         */
        public function addServer(string $host, int $port): bool;

        /**
         * Adds a list of servers to the list of servers to use
         *
         * @param array $servers An array of servers to connect to (eg; ['host:port', 'host:port', ...])
         * @return bool
         */
        public function addServers(array $servers): bool;

        /**
         * Processes a task in the background (does not return a result)
         *
         * @param Task $task The task to process
         * @return void
         */
        public function doBackground(Task $task): void;

        /**
         * Queues a task to be processed in parallel (returns a result handled by a callback)
         *
         * @param Task $task
         * @return void
         */
        public function addTask(Task $task): void;

        /**
         * Executes all tasks in the queue and waits for them to complete
         *
         * @return bool
         */
        public function run(): bool;

        /**
         * Returns True if the client is set to automatically reconnect to the server after a period of time
         *
         * @return bool
         */
        public function isAutomaticReconnect(): bool;

        /**
         * Enables or disables automatic reconnecting to the server after a period of time
         *
         * @param bool $automatic_reconnect
         * @return void
         */
        public function setAutomaticReconnect(bool $automatic_reconnect): void;
    }