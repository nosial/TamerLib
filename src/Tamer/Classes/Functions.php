<?php

    namespace Tamer\Classes;

    use InvalidArgumentException;
    use OptsLib\Parse;
    use Tamer\Abstracts\ProtocolType;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Interfaces\WorkerProtocolInterface;

    class Functions
    {
        /**
         * Attempts to get the worker id from the command line arguments or the environment variable TAMER_WORKER_ID
         * If neither are set, returns null.
         *
         * @return string|null
         */
        public static function getWorkerId(): ?string
        {
            $options = Parse::getArguments();

            $worker_id = ($options['worker-id'] ?? null);
            if($worker_id !== null)
                return $worker_id;

            $worker_id = getenv('TAMER_WORKER_ID');
            if($worker_id !== false)
                return $worker_id;

            return null;
        }

        /**
         * Constructs a client protocol object based on the protocol type
         *
         * @param string $protocol
         * @param string|null $username
         * @param string|null $password
         * @return ClientProtocolInterface
         */
        public static function createClient(string $protocol, ?string $username=null, ?string $password=null): ClientProtocolInterface
        {
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            return match (strtolower($protocol))
            {
                ProtocolType::Gearman => new \Tamer\Protocols\Gearman\Client($username, $password),
                ProtocolType::RabbitMQ => new \Tamer\Protocols\RabbitMq\Client($username, $password),
                default => throw new InvalidArgumentException('Invalid protocol type'),
            };
        }

        /**
         * @param string $protocol
         * @param string|null $username
         * @param string|null $password
         * @return WorkerProtocolInterface
         */
        public static function createWorker(string $protocol, ?string $username=null, ?string $password=null): WorkerProtocolInterface
        {
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            return match (strtolower($protocol))
            {
                ProtocolType::Gearman => new \Tamer\Protocols\Gearman\Worker($username, $password),
                ProtocolType::RabbitMQ => new \Tamer\Protocols\RabbitMq\Worker($username, $password),
                default => throw new InvalidArgumentException('Invalid protocol type'),
            };
        }
    }