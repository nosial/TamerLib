<?php

    require 'ncc';

    use Tamer\Objects\Job;

    import('net.nosial.tamerlib', 'latest');
    $worker = new \Tamer\Protocols\GearmanWorker();
    $worker->addServer();

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