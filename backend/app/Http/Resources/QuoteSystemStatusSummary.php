<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QuoteSystemStatus;

/**
 * The `{ id, name, color, behavior }` shape a quote system status takes inside
 * other resources (spec 0404 part A): working statuses, offers.
 */
final class QuoteSystemStatusSummary
{
    /**
     * @return array{id: int, name: string, color: string|null, behavior: string}|null
     */
    public static function of(?QuoteSystemStatus $status): ?array
    {
        if ($status === null) {
            return null;
        }

        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'behavior' => $status->behavior->value,
        ];
    }
}
