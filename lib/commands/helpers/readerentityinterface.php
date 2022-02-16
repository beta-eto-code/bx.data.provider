<?php

namespace BX\Data\Provider\Commands\Helpers;

use IteratorAggregate;

interface ReaderEntityInterface extends IteratorAggregate
{
    /**
     * @return array
     */
    public function getNames(): array;
}
