<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace TamerLib\Objects;

    use TamerLib\Abstracts\JobStatus;

    class JobResults
    {
        /**
         * The ID of the job
         *
         * @var string
         */
        private $id;

        /**
         * The data to be passed to the function
         *
         * @var string
         */
        private $data;

        /**
         * The status of the job
         *
         * @var int
         * @see JobStatus
         */
        private $status;

        public function __construct(?Job $job=null, ?int $status=null, $results=null)
        {
            if($job !== null)
            {
                $this->id = $job->getId();
                $this->data = $results;
                $this->status = $status;
            }
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
         * Returns the data of the Job
         *
         * @return string
         */
        public function getData(): string
        {
            return $this->data;
        }

        /**
         * @return int
         * @noinspection PhpUnused
         */
        public function getStatus(): int
        {
            return $this->status;
        }

        /**
         * Returns an array representation of the Job
         *
         * @return array
         */
        public function toArray(): array
        {
            return [
                'type' => 'tamer_job_results',
                'id' => $this->id,
                'data' => $this->data,
                'status' => $this->status
            ];
        }

        /**
         * Constructs a Job from an array
         *
         * @param array $data
         * @return JobResults
         */
        public static function fromArray(array $data): JobResults
        {
            $job = new JobResults();

            $job->setId($data['id']);
            $job->setData($data['data']);
            $job->setStatus($data['status']);

            return $job;
        }

        /**
         * @param string $id
         */
        protected function setId(string $id): void
        {
            $this->id = $id;
        }

        /**
         * @param string $data
         */
        protected function setData(string $data): void
        {
            $this->data = $data;
        }

        /**
         * @param int|null $status
         */
        protected function setStatus(?int $status): void
        {
            $this->status = $status;
        }


    }