<?php

    namespace Tamer\Exceptions;

    use Exception;

    class WorkerException extends Exception
    {
        /**
         * @param string $message
         * @param int $code
         * @param Exception|null $previous
         */
        public function __construct(string $message, int $code=0, Exception $previous=null)
        {
            parent::__construct($message, $code, $previous);
        }
    }