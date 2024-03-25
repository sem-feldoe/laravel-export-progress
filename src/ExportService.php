<?php

declare(strict_types=1);

namespace Atx\ExportProgress;

use Atx\ExportProgress\Contracts\ExportService as ExportServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

final class ExportService implements ExportServiceContract
{
    public function startExport(string $uuid, int|string|null $modelId = null): void
    {
        Cache::put($this->getCacheKey($uuid, $modelId), now()->timestamp, 3600);
    }

    public function getStartedAt(string $uuid, int|string|null $modelId = null): Carbon
    {
        $startAtTimestamp = Cache::get($this->getCacheKey($uuid, $modelId));
        if ($startAtTimestamp === null) {
            $this->startExport($uuid, $modelId);

            return now();
        }

        return Carbon::createFromTimestamp($startAtTimestamp);
    }

    public function endExport(string $uuid, int|string|null $modelId = null): void
    {
        Cache::forget($this->getCacheKey($uuid, $modelId));
    }

    public function calculateEstimatedFinishedTime(string $uuid, float $progress, int|string|null $modelId = null): Carbon
    {
        $startedAt = $this->getStartedAt($uuid, $modelId);
        $currentTime = Carbon::now();
        $elapsed = $startedAt->diffInSeconds($currentTime);
        $remainingSeconds = (1.0 - $progress) * ($elapsed / ($progress ?: 0.01));

        info('Debug: ', [
            'uuid' => $uuid,
            'modelId' => $modelId ?? 'null',
            'startedAt' => $startedAt->toDateTimeString(),
            'currentTime' => $currentTime->toDateTimeString(),
            'elapsed' => $elapsed,
            'progress' => $progress,
            'remainingSeconds' => $remainingSeconds,
        ]);

        if ($progress < 0.01) {
            return $currentTime->copy()->addSeconds(5 * 60);  // Estimation fixe de 3 minutes
        }

        return $currentTime->copy()->addSeconds((int) round($remainingSeconds));
    }

    private function getCacheKey(string $uuid, int|string|null $modelId = null): string
    {
        $formId = $modelId !== null ? "$modelId" : 'no_model';

        return 'export_started_at_'.$uuid.'_'.$formId;
    }
}
