<?php

    use Tamer\Abstracts\ProtocolType;
    use Tamer\Objects\JobResults;
    use Tamer\Objects\Task;
    use Tamer\Tamer;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    Tamer::init(ProtocolType::Gearman,
        ['127.0.0.1:4730']
    );

    Tamer::addWorker(__DIR__ . '/tamer_worker.php', 10);
    Tamer::startWorkers();


    // Sleep function (task) loop 10 times
    for ($i = 0; $i < 10; $i++)
    {
        Tamer::queue(Task::create('sleep', 5, function(JobResults $data)
        {
            echo "Slept for {$data->getData()} seconds \n";
        }));
    }

    echo "Waiting for jobs to finish \n";
    $a = microtime(true);
    Tamer::run();
    $b = microtime(true);
    echo "Took " . ($b - $a) . " seconds \n";