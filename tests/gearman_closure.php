<?php

    require 'ncc';

import('net.nosial.tamerlib', 'latest');

    $client = new \Tamer\Protocols\Gearman\Client();
    $client->addServer();

    $client->doClosure(function () {
        require 'ncc';
        import('net.nosial.loglib', 'latest');

        \LogLib\Log::info('gearman_closure.php', 'closure');
    });