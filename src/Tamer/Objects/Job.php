<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace Tamer\Objects;

    use Closure;
    use Opis\Closure\SerializableClosure;
    use function unserialize;

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
         * @var string|Closure|null
         */
        private $data;

        /**
         * Indicates if the data is a closure
         *
         * @var bool
         */
        private $closure;

        public function __construct(Task $task)
        {
            $this->id = $task->getId();
            $this->name = $task->getFunctionName();
            $this->data = $task->getData();
            $this->closure = $task->isClosure();
        }

        /**
         * Returns the ID of the Job
         *
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * Returns the function name of the Job
         *
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * Returns the data of the Job
         *
         * @return string|Closure|null
         */
        public function getData(): Closure|string|null
        {
            return $this->data;
        }

        /**
         * @return bool
         */
        public function isClosure(): bool
        {
            return $this->closure;
        }

        /**
         * Returns an array representation of the Job
         *
         * @return array
         */
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'data' => ($this->closure ? serialize(new SerializableClosure($this->data)) : $this->data),
                'closure' => $this->closure
            ];
        }

        /**
         * Constructs a Job from an array
         *
         * @param array $data
         * @return Job
         */
        public static function fromArray(array $data): Job
        {
            $job_data = $data['data'];

            if($data['closure'] === true)
            {
                /** @var SerializableClosure $job_data */
                $job_data = unserialize($data['data']);
                $job_data = $job_data->getClosure();
            }

            $job = new Job(new Task($data['name'], $job_data));
            $job->id = $data['id'];
            $job->closure = $data['closure'];

            return $job;
        }

    }