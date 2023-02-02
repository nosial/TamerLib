<?php

    namespace Tamer\Classes;

    use OptsLib\Parse;

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
    }