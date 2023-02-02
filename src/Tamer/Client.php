<?php

    namespace Tamer;

    use InvalidArgumentException;
    use Tamer\Classes\Validate;

    class Client
    {
        /**
         * @var string
         */
        private string $protocol;

        /**
         * Tamer client public constructor
         */
        public function __construct(string $protocol)
        {
            $this->setProtocol($protocol);
        }

        /**
         * @return string
         */
        public function getProtocol(): string
        {
            return $this->protocol;
        }

        /**
         * @param string $protocol
         */
        public function setProtocol(string $protocol): void
        {
            if(!Validate::protocolType($protocol))
            {
                throw new InvalidArgumentException("Invalid protocol type: $protocol");
            }

            $this->protocol = $protocol;
        }
    }