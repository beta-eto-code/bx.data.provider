<?php

namespace BX\Data\Provider;

use Data\Provider\QueryCriteria;

class ExtQueryCriteria extends QueryCriteria
{
    private array $runtimeList = [];

    /**
     * @return array
     */
    public function getRuntime(): array
    {
        return $this->runtimeList;
    }

    /**
     * @param array $runtimeList
     * @return void
     */
    public function setRuntime(array $runtimeList)
    {
        $this->runtimeList = $runtimeList;
    }
}
