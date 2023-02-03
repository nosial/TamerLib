<?php

    use Tamer\Abstracts\Mode;
    use Tamer\Abstracts\ProtocolType;
    use Tamer\Tamer;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    Tamer::connect(ProtocolType::Gearman, Mode::Client,
        ['127.0.0.1:4730']
    );

    // Pi calculation (closure)
    // Add it 10 times
    for($i = 0; $i < 100; $i++)
    {
        Tamer::queueClosure(function() {
            // Do Pi calculation
            $pi = 0;
            $top = 4.0;
            $bot = 1.0;
            $minus = true;

            for($i = 0; $i < 1000000; $i++)
            {
                if($minus)
                {
                    $pi -= ($top / $bot);
                    $minus = false;
                }
                else
                {
                    $pi += ($top / $bot);
                    $minus = true;
                }

                $bot += 2.0;
            }

            \LogLib\Log::info('net.nosial.tamerlib', sprintf('Pi: %s', $pi));
            return $pi;
        });
    }

    // Sleep function (task)
    Tamer::queue(\Tamer\Objects\Task::create('sleep', 5, function(\Tamer\Objects\JobResults $data)
    {
        echo "Slept for {$data->getData()} seconds \n";
    }));

    $a = microtime(true);
    Tamer::run();
    $b = microtime(true);

    echo "Took " . ($b - $a) . " seconds \n";