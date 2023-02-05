<?php

    namespace Tamer\Classes;

    use Exception;
    use InvalidArgumentException;
    use OptsLib\Parse;
    use Symfony\Component\Process\PhpExecutableFinder;
    use Tamer\Abstracts\ProtocolType;
    use Tamer\Interfaces\ClientProtocolInterface;
    use Tamer\Interfaces\WorkerProtocolInterface;

    class Functions
    {
        /**
         * A cache of the worker variables
         *
         * @var array|null
         */
        private static $worker_variables;

        /**
         * A cache of the php binary path
         *
         * @var string|null
         */
        private static $php_bin;

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

        /**
         * Returns the worker variables from the environment variables
         *
         * @return array
         */
        public static function getWorkerVariables(): array
        {
            if(self::$worker_variables == null)
            {
                self::$worker_variables = [
                    'TAMER_ENABLED' => getenv('TAMER_ENABLED') === 'true',
                    'TAMER_PROTOCOL' => getenv('TAMER_PROTOCOL'),
                    'TAMER_SERVERS' => getenv('TAMER_SERVERS'),
                    'TAMER_USERNAME' => getenv('TAMER_USERNAME'),
                    'TAMER_PASSWORD' => getenv('TAMER_PASSWORD'),
                    'TAMER_INSTANCE_ID' => getenv('TAMER_INSTANCE_ID'),
                ];

                if(self::$worker_variables['TAMER_SERVERS'] !== false)
                    self::$worker_variables['TAMER_SERVERS'] = explode(',', self::$worker_variables['TAMER_SERVERS']);
            }

            return self::$worker_variables;
        }

        /**
         * Returns the path to the php binary
         *
         * @return string
         * @throws Exception
         */
        public static function findPhpBin(): string
        {
            if(self::$php_bin !== null)
                return self::$php_bin;

            $php_finder = new PhpExecutableFinder();
            $php_bin = $php_finder->find();
            if($php_bin === false)
                throw new Exception('Unable to find the php binary');

            self::$php_bin = $php_bin;
            return $php_bin;
        }
    }