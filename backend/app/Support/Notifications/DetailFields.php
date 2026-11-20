<?php

declare(strict_types=1);

namespace App\Support\Notifications;

/**
 * The normalization every detail-card builder shares (RecordDetails,
 * TaskDetails and the per-module builders): a field with no value is
 * OMITTED, never rendered as an empty row, because a card half full of
 * dashes reads as broken data.
 */
final class DetailFields
{
    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, string>
     */
    public static function compact(array $fields): array
    {
        $details = [];

        foreach ($fields as $label => $value) {
            if ($value !== null && trim($value) !== '') {
                $details[$label] = trim($value);
            }
        }

        return $details;
    }
}
