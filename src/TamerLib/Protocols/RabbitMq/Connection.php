<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Protocols\RabbitMq;

    use Exception;
    use LogLib\Log;
    use PhpAmqpLib\Channel\AMQPChannel;
    use PhpAmqpLib\Connection\AMQPStreamConnection;
    use TamerLib\Exceptions\ConnectionException;

    class Connection
    {
        /**
         * The unique ID of the connection
         *
         * @var string
         */
        private $id;

        /**
         * The stream connection
         *
         * @var AMQPStreamConnection|null
         */
        private $connection;

        /**
         * The channel to use for the connection
         *
         * @var AMQPChannel|null
         */
        private $channel;

        /**
         * The host to connect to
         *
         * @var string
         */
        private $host;

        /**
         * The port to connect to
         *
         * @var int
         */
        private $port;

        /**
         * (Optional) The username to use when connecting to the server
         *
         * @var string|null
         */
        private $username;

        /**
         * (Optional) The password to use when connecting to the server
         *
         * @var string|null
         */
        private $password;

        /**
         * The Unix timestamp of when the next reconnect should occur
         *
         * @var int
         */
        private $next_reconnect;

        /**
         * @param string $host
         * @param int $port
         * @param string|null $username
         * @param string|null $password
         */
        public function __construct(string $host, int $port, ?string $username=null, ?string $password=null)
        {
            $this->id = uniqid();
            $this->host = $host;
            $this->port = $port;
            $this->username = $username;
            $this->password = $password;
        }

        /**
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return AMQPStreamConnection|null
         */
        public function getConnection(): ?AMQPStreamConnection
        {
            return $this->connection;
        }

        /**
         * @return AMQPChannel|null
         */
        public function getChannel(): ?AMQPChannel
        {
            return $this->channel;
        }

        /**
         * Returns True if the client is connected to the server
         *
         * @return bool
         */
        public function isConnected(): bool
        {
            return $this->connection !== null;
        }

        /**
         * Establishes a connection to the server
         *
         * @return void
         * @throws ConnectionException
         */
        public function connect(): void
        {
            if($this->isConnected())
            {
                return;
            }

            try
            {
                $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->username, $this->password);
                $this->channel = $this->connection->channel();
                $this->channel->queue_declare('tamer_queue', false, true, false, false);
                $this->next_reconnect = time() + 1800;
            }
            catch(Exception $e)
            {
                throw new ConnectionException(sprintf('Could not connect to RabbitMQ server: %s', $e->getMessage()), $e->getCode(), $e);
            }
        }

        /**
         * Closes the connection to the server
         *
         * @return void
         */
        public function disconnect(): void
        {
            if(!$this->isConnected())
            {
                return;
            }

            try
            {
                $this->channel?->close();
            }
            catch(Exception $e)
            {
                unset($e);
            }

            try
            {
                $this->connection?->close();
            }
            catch(Exception $e)
            {
                unset($e);
            }

            $this->channel = null;
            $this->connection = null;
        }

        /**
         * Reconnects to the server
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
         * The automatic reconnect process
         *
         * @return void
         */
        public function preformAutoreconf(): void
        {
            if($this->next_reconnect < time())
            {
                try
                {
                    $this->reconnect();
                }
                catch (Exception $e)
                {
                    Log::error('net.nosial.tamerlib', 'Could not reconnect to RabbitMQ server: %s', $e);
                }
                finally
                {
                    $this->next_reconnect = time() + 1800;
                }
            }
        }

    }