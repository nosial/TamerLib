<?php

    namespace TamerLib\Classes;

    use TamerLib\Abstracts\Mode;
    use TamerLib\Abstracts\ObjectType;
    use TamerLib\Abstracts\ProtocolType;
    use TamerLib\Abstracts\TaskPriority;

    class Validate
    {
        /**
         * Returns true if the input is a valid protocol type.
         *
         * @param string $input
         * @return bool
         */
        public static function protocolType(string $input): bool
        {
            return match (strtolower($input))
            {
                ProtocolType::Gearman, ProtocolType::RabbitMQ => true,
                default => false,
            };
        }

        /**
         * @param string $input
         * @return bool
         */
        public static function mode(string $input): bool
        {
            return match (strtolower($input))
            {
                Mode::Client, Mode::Worker => true,
                default => false,
            };
        }

        /**
         * Returns true if the input is a valid task priority.
         *
         * @param int $input
         * @return bool
         */
        public static function taskPriority(int $input): bool
        {
            return match ($input)
            {
                TaskPriority::Low, TaskPriority::Normal, TaskPriority::High => true,
                default => false,
            };
        }


        /**
         * Determines the object type
         *
         * @param $input
         * @return string
         */
        public static function getObjectType($input): string
        {
            if(!is_array($input))
            {
                return ObjectType::Unknown;
            }

            if(!array_key_exists('type', $input))
            {
                return ObjectType::Unknown;
            }

            return match ($input['type'])
            {
                ObjectType::Job => ObjectType::Job,
                ObjectType::JobResults => ObjectType::JobResults,
                default => ObjectType::Unknown,
            };
        }
    }