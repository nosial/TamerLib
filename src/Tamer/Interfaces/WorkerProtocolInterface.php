<?php

    namespace Tamer\Interfaces;

    interface WorkerProtocolInterface
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
         * @return void
         */
        public function addServers(array $servers): void;

        /**
         * Adds options to the worker (worker specific)
         *
         * @param array $options
         * @return bool
         */
        public function addOptions(array $options): bool;

        /**
         * Returns True if the worker is set to automatically reconnect to the server after a period of time
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
         * Registers a function to the worker
         *
         * @param string $function_name The name of the function to add
         * @param callable $function The function to add
         * @param mixed $context (optional) The context to pass to the function
         * @return void
         */
        public function addFunction(string $function_name, callable $function, mixed $context=null): void;

        /**
         * Removes a function from the worker
         *
         * @param string $function_name The name of the function to remove
         * @return void
         */
        public function removeFunction(string $function_name): void;

        /**
         * Works a job from the queue (blocking or non-blocking)
         *
         * @param bool $blocking (optional) Whether to block until a job is available
         * @param int $timeout (optional) The timeout to use when blocking
         * @param bool $throw_errors (optional) Whether to throw errors or not
         * @return void
         */
        public function work(bool $blocking=true, int $timeout=500, bool $throw_errors=false): void;
    }