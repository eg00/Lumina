<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaProcessedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $mediaFileUuid,
        public ?string $thumbnailPath = null,
        public ?string $previewPath = null,
        public ?string $dominantColor = null,
        public ?string $capturedAt = null,
    ) {
        //
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('gallery'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MediaProcessed';
    }
}
