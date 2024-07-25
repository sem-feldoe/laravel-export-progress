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

final class ExportCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        protected User $user,
        protected string $url,
        protected string $uuid,
        protected readonly ExportType $type
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('exports.'.$this->user->getKey()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'export.completed';
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getType(): ExportType
    {
        return $this->type;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function broadcastWith(): array
    {
        return [
            'url' => $this->url,
            'uuid' => $this->uuid,
            'type' => $this->type->value,
        ];
    }
}
