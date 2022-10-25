<?php

namespace BX\Data\Provider\Commands;

use Data\Provider\Interfaces\MigrateResultInterface;
use Data\Provider\Interfaces\StatisticMigrateResultInterface;

interface DataProviderTaskInterface
{
    /**
     * @return MigrateResultInterface|StatisticMigrateResultInterface
     */
    public function run();
}
