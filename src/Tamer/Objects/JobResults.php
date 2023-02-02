<?php

    namespace Tamer\Objects;

    class JobResults
    {
        /**
         * @var Task
         */
        private Task $task;

        /**
         * @var int
         */
        private int $status;

        /**
         * @var string|null
         */
        private ?string $result;

        /**
         * Public Constructor
         *
         * @param Task $task
         * @param int $status
         * @param string|null $result
         */
        public function __construct(Task $task, int $status, ?string $result)
        {
            $this->task = $task;
            $this->status = $status;
            $this->result = $result;
        }

        /**
         * @return Task
         */
        public function getTask(): Task
        {
            return $this->task;
        }

        /**
         * @return int
         */
        public function getStatus(): int
        {
            return $this->status;
        }

        /**
         * @return string|null
         */
        public function getResult(): ?string
        {
            return $this->result;
        }
    }