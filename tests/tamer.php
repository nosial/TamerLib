<?php


    use TamerLib\Abstracts\ProtocolType;
    use TamerLib\Tamer;

    require 'ncc';

    import('net.nosial.tamerlib', 'latest');

    Tamer::init(ProtocolType::Gearman,
        ['127.0.0.1:4730']
        //['127.0.0.1:5672'], 'guest', 'guest'
    );

    $instances = 10;
    Tamer::addWorker('closure', $instances);
    Tamer::startWorkers();
    $a = microtime(true);
    $times = [];
    $jobs = 30;

    // Pi function (closure) loop 10 times
    for ($i = 0; $i < $jobs; $i++)
    {
        Tamer::queueClosure(function(){
            // Full pi calculation implementation

            $start = microtime(true);
            $pi = 0;
            $top = 4;
            $bot = 1;
            $minus = true;
            $iterations = 1000000;

            for ($i = 0; $i < $iterations; $i++)
            {
                if ($minus)
                {
                    $pi = $pi - ($top / $bot);
                    $minus = false;
                }
                else
                {
                    $pi = $pi + ($top / $bot);
                    $minus = true;
                }

                $bot += 2;
            }

            return json_encode([$pi, $start]);
        },
            function($return) use ($a, &$times)
            {
                $return = json_decode($return, true);
                $end_time = microtime(true) - $return[1];
                $times[] = $end_time;
                echo "Pi is {$return[0]}, completed in " . ($end_time) . " seconds \n";
            });
    }

    echo "Waiting for $jobs jobs to finish on $instances workers \n";
    Tamer::run();
    $b = microtime(true);

    echo PHP_EOL;
    echo "Average time: " . (array_sum($times) / count($times)) . " seconds \n";
    echo "Took (with tamer)" . ($b - $a) . " seconds \n";
    echo "Total time (without tamer): " . (array_sum($times)) . " seconds \n";
    echo "Tamer overhead: " . (($b - $a) - array_sum($times)) . " seconds \n";