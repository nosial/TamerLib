<?php

    use Tamer\Abstracts\Mode;
    use Tamer\Abstracts\ProtocolType;
    use Tamer\Tamer;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    Tamer::initWorker();

    Tamer::addFunction('sleep', function(\Tamer\Objects\Job $job) {
        sleep($job->getData());
        return $job->getData();
    });

    Tamer::work();