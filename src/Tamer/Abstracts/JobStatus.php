<?php

    namespace Tamer\Abstracts;

    abstract class JobStatus
    {
        const Success = 0;

        const Failure = 1;

        const Exception = 2;
    }