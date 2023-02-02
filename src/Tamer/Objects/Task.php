<?php

    namespace Tamer\Objects;

    use Closure;
    use InvalidArgumentException;
    use Tamer\Abstracts\TaskPriority;
    use Tamer\Classes\Validate;

    class Task
    {
        /**
         * @var string
         */
        private $id;

        /**
         * @var string
         */
        private string $function_name;

        /**
         * @var string|Closure|null
         */
        private string|null|Closure $data;

        /**
         * @var int
         */
        private int $priority;

        /**
         * @var callable|null
         */
        private $callback;

        /**
         * @var bool
         */
        private $closure;

        /**
         * Public Constructor
         *
         * @param string $function_name
         * @param string|Closure|null $data
         * @param callable|null $callback
         */
        public function __construct(string $function_name, string|Closure|null $data, callable $callback=null)
        {
            $this->function_name = $function_name;
            $this->data = $data;
            $this->id = uniqid();
            $this->priority = TaskPriority::Normal;
            $this->callback = $callback;
            $this->closure = false;
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
         * @return string|Closure|null
         */
        public function getData(): string|null|Closure
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

        /**
         * @return bool
         */
        public function isClosure(): bool
        {
            return $this->closure;
        }

        /**
         * @param bool $closure
         */
        public function setClosure(bool $closure): void
        {
            $this->closure = $closure;
        }
    }