<?php


    use Tamer\Objects\Task;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    $client = new \Tamer\Protocols\RabbitMq\Client('guest', 'guest');
    $client->addServer('127.0.0.1', 5672);

    // Loop through 10 tasks

    for($i = 0; $i < 500; $i++)
    {
        $client->do(new Task('sleep', '5'));
    }
