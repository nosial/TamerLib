<?php

    require 'ncc';

    use Tamer\Objects\JobResults;
    use Tamer\Objects\Task;

    import('net.nosial.tamerlib', 'latest');

    $client = new \Tamer\Protocols\GearmanClient();
    $client->addServer();

    $client->doBackground(new Task('sleep', '5'));


    $client->addTask(new Task('sleep', '5', function(JobResults $job) {
        echo "Task {$job->getId()} completed with data: {$job->getData()} \n";
    }));


    $client->addTask(new Task('sleep', '5', function(JobResults $job) {
        echo "Task {$job->getId()} completed with data: {$job->getData()} \n";
    }));


    $client->run();