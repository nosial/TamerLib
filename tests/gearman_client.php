<?php

    require 'ncc';

use Tamer\Objects\JobResults;
use Tamer\Objects\Task;

import('net.nosial.tamerlib', 'latest');

    $client = new \Tamer\Protocols\Gearman\Client();
    $client->addServer();

    $client->do(new Task('sleep', '5'));


    $client->queue(new Task('sleep', '5', function(JobResults $job) {
        echo "Task {$job->getId()} completed with data: {$job->getData()} \n";
    }));


    $client->queue(new Task('sleep', '5', function(JobResults $job) {
        echo "Task {$job->getId()} completed with data: {$job->getData()} \n";
    }));


    $client->run();