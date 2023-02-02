<?php

    namespace Tamer\Objects;

    use InvalidArgumentException;
    use Tamer\Abstracts\TaskPriority;
    use Tamer\Classes\Validate;

    class Task
    {
        /**
         * @var string
         */
        private string $id;

        /**
         * @var string
         */
        private string $function_name;

        /**
         * @var string
         */
        private string $data;

        /**
         * @var int
         */
        private int $priority;

        /**
         * @var callable|null
         */
        private $callback;

        /**
         * Public Constructor
         *
         * @param string $function_name
         * @param string $data
         * @param callable|null $callback
         */
        public function __construct(string $function_name, string $data, callable $callback=null)
        {
            $this->function_name = $function_name;
            $this->data = $data;
            $this->id = uniqid();
            $this->priority = TaskPriority::Normal;
            $this->callback = $callback;
        }

        /**
         * Returns the function name for the task
         *
         * @return string
         */
        public function getFunctionName(): string
        {
            return $this->function_name;
        }

        /**
         * Sets the function name for the task
         *
         * @param string $function_name
         * @return Task
         */
        public function setFunctionName(string $function_name): self
        {
            $this->function_name = $function_name;
            return $this;
        }

        /**
         * Returns the arguments for the task
         *
         * @return string
         */
        public function getData(): string
        {
            return $this->data;
        }

        /**
         * Sets the arguments for the task
         *
         * @param string $data
         * @return Task
         */
        public function setData(string $data): self
        {
            $this->data = $data;
            return $this;
        }

        /**
         * Returns the Unique ID of the task
         *
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return int
         */
        public function getPriority(): int
        {
            return $this->priority;
        }

        /**
         * @param int $priority
         * @return Task
         */
        public function setPriority(int $priority): self
        {
            if(!Validate::taskPriority($priority))
            {
                throw new InvalidArgumentException("Invalid priority value");
            }

            $this->priority = $priority;
            return $this;
        }

        /**
         * @param callable|null $callback
         */
        public function setCallback(?callable $callback): void
        {
            $this->callback = $callback;
        }

        /**
         * Executes the callback function
         *
         * @param JobResults $result
         * @return void
         */
        public function runCallback(JobResults $result): void
        {
            if($this->callback !== null)
            {
                call_user_func($this->callback, $result);
            }
        }
    }