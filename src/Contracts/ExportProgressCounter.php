<?php

declare(strict_types=1);

namespace Atx\ExportProgress\Contracts;

interface ExportProgressCounter
{
    public function increment(string $uuid, ?int $modelId = null): void;

    public function getCounter(string $uuid, ?int $modelId = null): int;

    public function clearCounter(string $uuid, ?int $modelId = null): void;
}
