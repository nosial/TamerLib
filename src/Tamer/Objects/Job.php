<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Objects;

    class Job
    {
        /**
         * The ID of the job
         *
         * @var string
         */
        private $id;

        /**
         * The name of the function
         *
         * @var string
         */
        private $name;

        /**
         * The data to be passed to the function
         *
         * @var string
         */
        private $data;

        public function __construct(string $id, string $name, string $data)
        {
            $this->id = $id;
            $this->name = $name;
            $this->data = $data;
        }

        /**
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * @return string
         */
        public function getData(): string
        {
            return $this->data;
        }
    }