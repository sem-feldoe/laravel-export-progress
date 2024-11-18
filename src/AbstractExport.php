<?php

declare(strict_types=1);

namespace Atx\ExportProgress;

use App\Enums\ExportType;
use App\Enums\SupportedLocale;
use App\Models\User;
use Atx\ExportProgress\Contracts\ExportProgressCounter;
use Atx\ExportProgress\Contracts\ExportService;
use Atx\ExportProgress\Events\ExportFailed;
use Atx\ExportProgress\Events\ExportProgressed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Throwable;

abstract class AbstractExport implements HasLocalePreference, ShouldAutoSize, ShouldQueue, WithColumnFormatting, WithCustomChunkSize, WithEvents, WithHeadings, WithMapping
{
    use Exportable, RegistersEventListeners;

    private float $lastProgressSent = 0.0;

    private ?int $total = null;

    public function __construct(
        protected ?User $user,
        protected string $uuid,
        protected ExportType $type,
        protected SupportedLocale $locale,
        protected Collection $filters,
        private readonly Model|string|null $model,
        private readonly ExportProgressCounter $counter,
        private readonly ExportService $exportService,

    ) {}

    public function middleware(): array
    {
        return [new RateLimited($this->type->value)];
    }

    public function preferredLocale(): ?string
    {
        return $this->locale->value;
    }

    /**
     * @throws \Exception
     */
    protected function sendProgressEventIfNeeded(): void
    {
        $currentProgress = $this->getProgress();
        if ($currentProgress - $this->lastProgressSent >= 0.01) {
            if ($this->user instanceof User) {
                ExportProgressed::dispatch(
                    $this->user,
                    $this->uuid,
                    $this->type,
                    $this->model,
                    $currentProgress,
                    $this->exportService->calculateEstimatedFinishedTime($this->uuid, $currentProgress,
                        $this->getKeyFromModel())
                );
            }
            $this->lastProgressSent = $currentProgress;
        }
    }

    public function start(): void
    {
        $this->exportService->startExport($this->uuid, $this->getKeyFromModel());
        Log::debug('starting ', [
            'uuid' => $this->uuid,
            'model' => $this->getKeyFromModel() ?? 'null',
            'at' => $this->exportService->getStartedAt($this->uuid, $this->getKeyFromModel())->toDateTimeString(),
        ]);
    }

    public function stop(): void
    {
        $this->exportService->endExport($this->uuid, $this->getKeyFromModel());
        Log::debug('stopping ', [
            'uuid' => $this->uuid,
            'model' => $this->getKeyFromModel() ?? 'null',
        ]);
    }

    private function getProgressCount(bool $increment = false): int
    {
        if ($increment) {
            $this->counter->increment($this->uuid, $this->getKeyFromModel());
        }

        return $this->counter->getCounter($this->uuid, $this->getKeyFromModel());
    }

    private function getProgress(): float
    {
        $total = $this->getTotal();
        $progressCount = $this->getProgressCount(true);

        return $total > 0 ? $progressCount / $total : 0;
    }

    private function getTotal(): int
    {
        if (is_null($this->total)) {
            if ($this instanceof FromQuery) {
                $this->total = $this->query() instanceof Builder ? $this->query()->count() : 0;
            } elseif ($this instanceof FromCollection) {
                $this->total = $this->collection()->count();
            } else {
                $this->total = 0;
            }
        }

        return $this->total;
    }

    public function clearCounter(): void
    {
        $this->counter->clearCounter($this->uuid, $this->getKeyFromModel());
    }

    public static function beforeSheet(BeforeSheet $event): void
    {
        $sheet = $event->getConcernable();
        if ($sheet instanceof static) {
            $sheet->start();
        }
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->getConcernable();
        if ($sheet instanceof static) {
            $sheet->clearCounter();
            $sheet->stop();
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->user instanceof User) {
            ExportFailed::dispatch($this->uuid, $this->type, $this->user, $exception);
        }
    }

    private function getKeyFromModel(): string|int|null
    {
        if ($this->model instanceof Model) {
            return $this->model->getKey();
        }
        if (is_string($this->model)) {
            return $this->model;
        }

        return null;
    }
}
