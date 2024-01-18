<?php

declare(strict_types=1);

namespace Atx\ExportProgress\Contracts;

use Carbon\Carbon;

interface ExportService
{
    public function startExport(string $uuid, ?int $modelId = null): void;

    public function getStartedAt(string $uuid, ?int $modelId = null): Carbon;

    public function endExport(string $uuid, ?int $modelId = null): void;

    public function calculateEstimatedFinishedTime(string $uuid, float $progress, ?int $modelId = null): Carbon;
}
