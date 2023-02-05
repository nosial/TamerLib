<?php

    namespace TamerLib\Abstracts\ExitCodes;

    class WorkerExitCodes
    {
        const GracefulShutdown = 0;

        const Exception = 1;

        const UnsupervisedWorker = 2;

        const ProtocolUnavailable = 3;

        const ServerConnectionFailed = 4;
    }