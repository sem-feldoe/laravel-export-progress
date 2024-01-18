<?php

declare(strict_types=1);

namespace Atx\ExportProgress\Events;

use App\Enums\ExportType;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ExportFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        protected string $uuid,
        protected ExportType $type,
        protected User $user,
        protected Throwable $reason
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
        return 'export.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'reason' => [
                'code' => $this->reason->getCode(),
                'message' => $this->reason->getMessage(),
            ],
        ];
    }
}
