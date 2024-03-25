<?php

declare(strict_types=1);

namespace Atx\ExportProgress\Contracts;

interface ExportProgressCounter
{
    public function increment(string $uuid, int|string|null $modelId = null): void;

    public function getCounter(string $uuid, int|string|null $modelId = null): int;

    public function clearCounter(string $uuid, int|string|null $modelId = null): void;
}
