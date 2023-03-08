<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Classes;

    use Exception;
    use LogLib\Log;

    class Configuration
    {
        /**
         * The configuration data cache
         *
         * @var array|null
         */
        private static $configuration;

        /**
         * Returns the configuration data, or the default values if the configuration file does not exist
         *
         * @return array
         */
        public static function getConfiguration(): array
        {
            if(self::$configuration !== null)
            {
                return self::$configuration;
            }

            $configuration = new \ConfigLib\Configuration('tamer');
            $configuration = self::setDefaultValues($configuration);
            self::$configuration = $configuration->getConfiguration();

            return self::$configuration;
        }

        /**
         * Sets the default values for the configuration
         *
         * @param \ConfigLib\Configuration $configuration
         * @return \ConfigLib\Configuration
         */
        private static function setDefaultValues(\ConfigLib\Configuration $configuration): \ConfigLib\Configuration
        {
            $configuration->setDefault('protocol', 'gearman');
            $configuration->setDefault('username', null);
            $configuration->setDefault('password', null);
            $configuration->setDefault('servers', [
                '127.0.0.1:4730'
            ]);

            try
            {
                $configuration->save();
            }
            catch(Exception $e)
            {
                Log::warning('net.nosial.tamerlib', 'Unable to save the configuration file: ' . $e->getMessage());
            }

            return $configuration;
        }
    }