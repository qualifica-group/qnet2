<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Author of an AI assistant chat message (spec 0347).
 */
enum AiMessageRole: string
{
    use HasMeta;

    #[Label('User')]
    #[Color('blue')]
    case User = 'user';

    #[Label('Assistant')]
    #[Color('violet')]
    case Assistant = 'assistant';
}
