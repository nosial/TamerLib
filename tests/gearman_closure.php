<?php

    require 'ncc';

    use Tamer\Objects\JobResults;
    use Tamer\Objects\Task;

    import('net.nosial.tamerlib', 'latest');

    $client = new \Tamer\Protocols\GearmanClient();
    $client->addServer();

    $client->closure(function () {
        echo "This function was sent from a client, it should be executed on the worker";
    });