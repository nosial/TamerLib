<?php

    namespace Tamer\Classes;

    use Tamer\Abstracts\ProtocolType;
    use Tamer\Abstracts\TaskPriority;

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
                ProtocolType::Gearman, ProtocolType::RabbitMQ, ProtocolType::Redis => true,
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
    }