<?php


    // Pi function (closure) loop 10 times
    for ($i = 0; $i < 50; $i++)
    {
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

    }