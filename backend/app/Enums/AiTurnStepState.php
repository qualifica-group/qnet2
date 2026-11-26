<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * State of one step of a turn (spec 0433, `AiTurnStep.state`), stored as its value in the `steps` column.
 */
enum AiTurnStepState: string
{
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}
