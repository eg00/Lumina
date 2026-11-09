<?php

declare(strict_types=1);

namespace App\Enums;

enum TagType: string
{
    case User = 'user';
    case Event = 'event';
    case AiDetected = 'ai_detected';
}
