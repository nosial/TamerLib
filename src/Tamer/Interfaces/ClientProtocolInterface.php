<?php

    namespace Tamer\Interfaces;

    use Closure;
    use Tamer\Objects\Task;

    interface ClientProtocolInterface
    {
        /**
         * Public Constructor with optional username and password
         *
         * @param string|null $username (optional) The username to use when connecting to the server (if required)
         * @param string|null $password (optional) The password to use when connecting to the server
         */
        public function __construct(?string $username=null, ?string $password=null);

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
         * Adds options to the client (client specific)
         *
         * @param array $options
         * @return bool
         */
        public function addOptions(array $options): bool;

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

        /**
         * Processes a task in the background (does not return a result)
         *
         * @param Task $task The task to process
         * @return void
         */
        public function do(Task $task): void;

        /**
         * Executes a closure operation in the background (does not return a result)
         *
         * @param Closure $closure The closure operation to perform (remote)
         * @return void
         */
        public function doClosure(Closure $closure): void;

        /**
         * Queues a task to be processed in parallel (returns a result handled by a callback)
         *
         * @param Task $task
         * @return void
         */
        public function queue(Task $task): void;

        /**
         * Queues a closure to be processed in parallel (returns a result handled by a callback)
         *
         * @param Closure $closure The closure operation to perform (remote)
         * @param Closure $callback The closure to call when the operation is complete (local)
         * @return void
         */
        public function queueClosure(Closure $closure, Closure $callback): void;

        /**
         * Executes all tasks in the queue and waits for them to complete
         *
         * @return bool
         */
        public function run(): bool;
    }