<?php

declare(strict_types=1);

namespace Atx\ExportProgress;

use Atx\ExportProgress\Contracts\ExportService as ExportServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        Cache::forget($this->getEtaCacheKey($uuid, $modelId));
    }

    public function calculateEstimatedFinishedTime(string $uuid, float $progress, int|string|null $modelId = null): Carbon
    {
        $startedAt = $this->getStartedAt($uuid, $modelId);
        $currentTime = Carbon::now();

        $progress = max(0.0, min(1.0, $progress));
        if ($progress >= 1.0) {
            return $currentTime;
        }

        $elapsedSeconds = max(0.0, (float) $startedAt->diffInRealMilliseconds($currentTime) / 1000.0);

        $totalSecondsEstimate = $elapsedSeconds / $progress;
        $remainingSecondsRaw = max(0.0, $totalSecondsEstimate - $elapsedSeconds);

        $etaKey = $this->getEtaCacheKey($uuid, $modelId);
        $previousRemaining = Cache::get($etaKey);

        $alpha = 0.25;
        $remainingSecondsSmoothed = is_numeric($previousRemaining)
            ? ((1.0 - $alpha) * (float) $previousRemaining + $alpha * $remainingSecondsRaw)
            : $remainingSecondsRaw;

        Cache::put($etaKey, $remainingSecondsSmoothed, 3600);

        Log::debug('calculateEstimatedFinishedTime', [
            'uuid' => $uuid,
            'modelId' => $modelId ?? 'null',
            'startedAt' => $startedAt->toDateTimeString(),
            'currentTime' => $currentTime->toDateTimeString(),
            'elapsedSeconds' => $elapsedSeconds,
            'progress' => $progress,
            'remainingSecondsRaw' => $remainingSecondsRaw,
            'remainingSecondsSmoothed' => $remainingSecondsSmoothed,
        ]);

        return $currentTime->copy()->addSeconds((int) round($remainingSecondsSmoothed));
    }

    private function getCacheKey(string $uuid, int|string|null $modelId = null): string
    {
        $formId = $modelId !== null ? "$modelId" : 'no_model';

        return 'export_started_at_'.$uuid.'_'.$formId;
    }

    private function getEtaCacheKey(string $uuid, int|string|null $modelId = null): string
    {
        $formId = $modelId !== null ? (string) $modelId : 'no_model';

        return 'export_eta_remaining_' . $uuid . '_' . $formId;
    }
}
