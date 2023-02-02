<?php

    namespace Tamer\Abstracts;

    abstract class ProtocolType
    {
        const Gearman = 'gearman';

        const RabbitMQ = 'rabbitmq';

        const Redis = 'redis';
    }