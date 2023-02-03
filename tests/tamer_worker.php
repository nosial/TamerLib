<?php

    use Tamer\Abstracts\Mode;
    use Tamer\Abstracts\ProtocolType;
    use Tamer\Tamer;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    Tamer::connect(ProtocolType::Gearman, Mode::Worker,
        ['127.0.0.1:4730']
    );

    Tamer::addFunction('sleep', function(\Tamer\Objects\Job $job) {
        sleep($job->getData());
        return $job->getData();
    });

    Tamer::work();