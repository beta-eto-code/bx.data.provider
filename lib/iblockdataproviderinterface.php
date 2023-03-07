<?php

namespace BX\Data\Provider;

interface IblockDataProviderInterface
{
    public function getIblockId(): int;
    public function getIblockCode(): string;
    public function getIblockType(): string;
}
