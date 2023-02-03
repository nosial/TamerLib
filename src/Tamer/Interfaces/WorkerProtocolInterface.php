<?php

    namespace Tamer\Interfaces;

    use Tamer\Exceptions\ConnectionException;

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
         * @return void
         */
        public function addServer(string $host, int $port): void;

        /**
         * Adds a list of servers to the list of servers to use
         *
         * @param array $servers An array of servers to connect to (eg; ['host:port', 'host:port', ...])
         * @return void
         */
        public function addServers(array $servers): void;

        /**
         * Connects to all the configured servers
         *
         * @throws ConnectionException
         * @return void
         */
        public function connect(): void;

        /**
         * Disconnects from all the configured servers
         *
         * @return void
         */
        public function disconnect(): void;

        /**
         * Reconnects to all the configured servers
         *
         * @throws ConnectionException
         * @return void
         */
        public function reconnect(): void;

        /**
         * Returns True if the client is connected to the server (or servers)
         *
         * @return bool
         */
        public function isConnected(): bool;

        /**
         * Sets options to the worker (worker specific)
         *
         * @param array $options
         * @return void
         */
        public function setOptions(array $options): void;

        /**
         * Returns the options set on the worker
         *
         * @return array
         */
        public function getOptions(): array;

        /**
         * Clears all options from the worker
         *
         * @return void
         */
        public function clearOptions(): void;

        /**
         * Returns True if the worker is set to automatically reconnect to the server after a period of time
         *
         * @return bool
         */
        public function automaticReconnectionEnabled(): bool;

        /**
         * Enables or disables automatic reconnecting to the server after a period of time
         *
         * @param bool $enable
         * @return void
         */
        public function enableAutomaticReconnection(bool $enable): void;

        /**
         * Registers a function to the worker
         *
         * @param string $name The name of the function to add
         * @param callable $callable The function to add
         * @return void
         */
        public function addFunction(string $name, callable $callable): void;

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