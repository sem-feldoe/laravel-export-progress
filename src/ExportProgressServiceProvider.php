<?php

declare(strict_types=1);

namespace Atx\ExportProgress;

use Atx\ExportProgress\Contracts\ExportProgressCounter as ExportProgressCounterContract;
use Atx\ExportProgress\Contracts\ExportService as ExportServiceContract;
use Illuminate\Support\ServiceProvider;

final class ExportProgressServiceProvider extends ServiceProvider
{
    public array $singletons = [
        ExportProgressCounterContract::class => ExportProgressCounter::class,
        ExportServiceContract::class => ExportService::class,
    ];
}
