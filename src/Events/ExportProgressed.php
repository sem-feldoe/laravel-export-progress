<?php

declare(strict_types=1);

namespace Atx\ExportProgress\Events;

use App\Enums\ExportType;
use App\Enums\SupportedLocale;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ExportProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        protected User $user,
        protected string $uuid,
        protected ExportType $type,
        protected ?Model $model,
        protected float $progress,
        protected ?Carbon $estimatedDuration = null
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exports.'.$this->user->getKey()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'export.progressed';
    }

    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'model' => $this->model instanceof Model ? [
                'id' => $this->model->getKey(),
                'name' => $this->model->title ?? $this->model->name ?? null,
            ] : null,
            'progress' => round($this->progress, 2),
            'estimated_duration' => $this->getLocalizedEstimatedDuration($this->estimatedDuration),
            'estimated_finished_time' => $this->estimatedDuration?->toDateTimeString(),
        ];
    }

    private function getLocalizedEstimatedDuration(?Carbon $estimatedDuration): array
    {
        $durations = [];
        foreach (SupportedLocale::cases() as $locale) {
            $durations[$locale->value] = $estimatedDuration?->locale($locale->value)
                ->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE);
        }

        return $durations;
    }
}
