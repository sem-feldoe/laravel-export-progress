<?php

declare(strict_types=1);

namespace Atx\ExportProgress;

use Atx\ExportProgress\Contracts\ExportProgressCounter as ExportProgressCounterContract;
use Illuminate\Support\Facades\Cache;

final class ExportProgressCounter implements ExportProgressCounterContract
{
    public function increment(string $uuid, int|string|null $modelId = null): void
    {
        $key = $this->getCacheKey($uuid, $modelId);

        Cache::increment($key);
    }

    public function getCounter(string $uuid, int|string|null $modelId = null): int
    {
        $key = $this->getCacheKey($uuid, $modelId);

        // @phpstan-ignore-next-line
        return (int) Cache::get($key, 0);
    }

    public function clearCounter(string $uuid, int|string|null $modelId = null): void
    {
        $key = $this->getCacheKey($uuid, $modelId);

        Cache::forget($key);
    }

    private function getCacheKey(string $uuid, int|string|null $modelId): string
    {
        if ($modelId === null) {
            $modelId = 'no_model';
        }

        return "export_progress_counter_{$uuid}_{$modelId}";
    }
}
