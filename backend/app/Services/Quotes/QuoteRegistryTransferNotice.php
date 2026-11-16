<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use Spatie\Activitylog\Models\Activity;

/**
 * Spec 0404 B-D5: the "Trasferita da A a B il gg/mm/aaaa da Nome" notice of the
 * offer detail, read off the latest `registry_transferred` activity entry of
 * the offer (the names are the ones stored at transfer time, so a later rename
 * does not rewrite history).
 */
final class QuoteRegistryTransferNotice
{
    /**
     * @return array{from_registry_name: string|null, to_registry_name: string|null, transferred_at: string|null, causer_name: string|null}|null
     */
    public function latestFor(Quote $quote): ?array
    {
        $entry = Activity::query()
            ->with('causer')
            ->where('subject_type', $quote->getMorphClass())
            ->where('subject_id', $quote->getKey())
            ->where('event', QuoteRegistryTransferService::EVENT)
            ->latest('id')
            ->first();

        if ($entry === null) {
            return null;
        }

        return [
            'from_registry_name' => $entry->properties->get('from')['name'] ?? null,
            'to_registry_name' => $entry->properties->get('to')['name'] ?? null,
            'transferred_at' => $entry->created_at?->toIso8601String(),
            'causer_name' => $entry->causer?->getAttribute('name'),
        ];
    }
}
