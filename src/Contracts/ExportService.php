<?php

declare(strict_types=1);

namespace Atx\ExportProgress\Contracts;

use Carbon\Carbon;

interface ExportService
{
    public function startExport(string $uuid, int|string|null $modelId = null): void;

    public function getStartedAt(string $uuid, int|string|null $modelId = null): Carbon;

    public function endExport(string $uuid, int|string|null $modelId = null): void;

    public function calculateEstimatedFinishedTime(string $uuid, float $progress, int|string|null $modelId = null): Carbon;
}
