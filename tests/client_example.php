<?php

    require 'ncc';

    use Tamer\Objects\Job;
    use Tamer\Objects\Task;

    import('net.nosial.tamerlib', 'latest');

    $client = new \Tamer\Protocols\GearmanClient();
    $client->addServer();

    $client->addTask(new Task('sleep', '5', function(Job $job) {
        echo "Task {$job->getId()} completed with data: {$job->getData()} \n";
    }));


    $client->doTasks();