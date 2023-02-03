<?php

    require 'ncc';

    use Tamer\Objects\Job;

    import('net.nosial.tamerlib', 'latest');
    $worker = new \Tamer\Protocols\RabbitMq\Worker('guest', 'guest');
    $worker->addServer('127.0.0.1', 5672);

    $worker->addFunction('sleep', function($job) {
        /** @var Job $job */
        var_dump(get_class($job));
        echo "Task {$job->getId()} started with data: {$job->getData()} \n";
        sleep($job->getData());
        echo "Task {$job->getId()} completed with data: {$job->getData()} \n";

        return $job->getData();
    });



    while(true)
    {
        echo "Waiting for job... \n";
        $worker->work();
    }