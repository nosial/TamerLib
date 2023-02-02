<?php

    namespace Tamer\Exceptions;

    use Exception;

    class ServerException extends Exception
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