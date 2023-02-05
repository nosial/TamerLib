<?php

    use TamerLib\Abstracts\Mode;
    use TamerLib\Abstracts\ProtocolType;
    use TamerLib\Tamer;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    Tamer::initWorker();

    Tamer::addFunction('sleep', function(\TamerLib\Objects\Job $job) {
        sleep($job->getData());
        return $job->getData();
    });

    Tamer::work();