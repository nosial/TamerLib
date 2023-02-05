<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Objects;

    use Closure;
    use InvalidArgumentException;
    use TamerLib\Abstracts\TaskPriority;
    use TamerLib\Classes\Validate;

    class Task
    {
        /**
         * @var string
         */
        private $id;

        /**
         * @var string
         */
        private $function_name;

        /**
         * @var string|Closure|null
         */
        private $data;

        /**
         * @var int
         */
        private $priority;

        /**
         * @var Closure|null
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
         * @param Closure|null $callback
         */
        public function __construct(string $function_name, string|Closure|null $data, Closure $callback=null)
        {
            $this->function_name = $function_name;
            $this->data = $data;
            $this->id = uniqid();
            $this->priority = TaskPriority::Normal;
            $this->callback = $callback;
            $this->closure = false;
        }

        /**
         * Static Constructor
         *
         * @param string $function_name
         * @param string|Closure|null $data
         * @param callable|null $callback
         * @return static
         */
        public static function create(string $function_name, string|Closure|null $data, callable $callback=null): self
        {
            return new self($function_name, $data, $callback);
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
         * @param Closure|null $callback
         * @return Task
         */
        public function setCallback(?Closure $callback): self
        {
            $this->callback = $callback;
            return $this;
        }

        /**
         * Executes the callback function
         *
         * @param string|JobResults|null $result
         * @return void
         */
        public function runCallback(string|JobResults|null $result): void
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
         * @return Task
         */
        public function setClosure(bool $closure): self
        {
            $this->closure = $closure;
            return $this;
        }
    }