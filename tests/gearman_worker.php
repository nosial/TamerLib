<?php

    require 'ncc';
    use Tamer\Objects\Task;

    import('net.nosial.tamerlib', 'latest');
    $worker = new \Tamer\Protocols\GearmanWorker();
    $worker->addServer();

    $worker->addFunction('sleep', function($task) {
        var_dump(get_class($task));
        echo "Task {$task->getId()} started with data: {$task->getData()} \n";
        sleep($task->getData());
        echo "Task {$task->getId()} completed with data: {$task->getData()} \n";
    });



    while(true)
    {
        echo "Waiting for job... \n";
        $worker->work();
    }