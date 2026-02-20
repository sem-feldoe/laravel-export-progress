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
        Cache::forget($this->getEtaCacheKey($uuid, $modelId));
        Cache::forget($this->getEtaCacheKey($uuid, $modelId).':meta');
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
        Cache::forget($this->getEtaCacheKey($uuid, $modelId).':meta');
    }

    public function calculateEstimatedFinishedTime(string $uuid, float $progress, int|string|null $modelId = null): Carbon
    {
        $startedAt = $this->getStartedAt($uuid, $modelId);
        $currentTime = Carbon::now();

        if ($progress > 1.0) {
            $progress = $progress / 100.0;
        }
        $progress = max(0.0, min(1.0, $progress));
        if ($progress >= 1.0) {
            return $currentTime;
        }

        $elapsedSeconds = max(0.0, (float) $startedAt->diffInRealMilliseconds($currentTime) / 1000.0);

        $totalSecondsEstimate = $elapsedSeconds / max($progress, 1e-6);
        $remainingSecondsRaw = max(0.0, $totalSecondsEstimate - $elapsedSeconds);

        $etaKey = $this->getEtaCacheKey($uuid, $modelId);
        $etaMetaKey = $etaKey.':meta';

        $previousRemainingSeconds = Cache::get($etaKey);
        $previousRemainingSeconds = is_numeric($previousRemainingSeconds) ? (float) $previousRemainingSeconds : null;

        $meta = Cache::get($etaMetaKey);
        $prevAt = (is_array($meta) && isset($meta['t']) && is_numeric($meta['t'])) ? (float) $meta['t'] : null;
        $seeded = is_array($meta) && array_key_exists('seeded', $meta) && (bool) $meta['seeded'];

        $t = microtime(true);
        $dt = ($prevAt !== null) ? max(0.0, $t - $prevAt) : 0.0;

        if ($previousRemainingSeconds !== null) {
            $previousRemainingSeconds = max(0.0, $previousRemainingSeconds - $dt);
        }

        $nearEndByProgress = $progress >= 0.98;
        $nearEndBySeconds = $remainingSecondsRaw <= 8.0;

        $needReseed = ($previousRemainingSeconds === null)
            || ($previousRemainingSeconds < 1.0 && $remainingSecondsRaw > 10.0);

        if (!$seeded && $remainingSecondsRaw > 0.0) {
            $remainingSecondsSmoothed = $remainingSecondsRaw;
            $seeded = true;
        } elseif ($nearEndByProgress || $nearEndBySeconds || $needReseed) {
            $remainingSecondsSmoothed = $remainingSecondsRaw;
        } else {
            $alpha = 0.40;
            $remainingSecondsSmoothed = ((1.0 - $alpha) * $previousRemainingSeconds + $alpha * $remainingSecondsRaw);
        }

        if ($seeded) {
            Cache::put($etaKey, $remainingSecondsSmoothed, 3600);
        }
        Cache::put($etaMetaKey, ['t' => $t, 'seeded' => $seeded], 3600);

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

        return 'export_eta_remaining_'.$uuid.'_'.$formId;
    }
}
