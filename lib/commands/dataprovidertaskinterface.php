<?php

namespace BX\Data\Provider\Commands;

use Data\Provider\Interfaces\MigrateResultInterface;

interface DataProviderTaskInterface
{
    /**
     * @return MigrateResultInterface
     */
    public function run(): MigrateResultInterface;
}
