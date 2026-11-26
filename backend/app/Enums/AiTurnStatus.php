<?php

namespace App\Enums;

/**
 * State of an AI assistant turn (spec 0433, state machine). The last four are final.
 */
enum AiTurnStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case LimitReached = 'limit_reached';

    public function isFinal(): bool
    {
        return ! in_array($this->value, self::activeValues(), true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Queued->value, self::Running->value, self::AwaitingConfirmation->value];
    }
}
