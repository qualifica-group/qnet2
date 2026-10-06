<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;

/**
 * Email + PEC recipient suggestions out of a personal-data card, shared by
 * every EmailOwner that suggests a registry's/referent's contacts (work
 * orders, invoices).
 */
final class ContactSuggestionBuilder
{
    /**
     * `value`/`normalized_value` are hidden by default on Contact (personal
     * data) -- made visible here, for THIS response only, never persisted.
     *
     * @return array<int, array{email: string, label: string, source: string}>
     */
    public function fromPersonalData(?PersonalData $personalData, string $ownerName, string $source): array
    {
        if ($personalData === null) {
            return [];
        }

        return $personalData->contacts
            ->whereIn('type', [ContactTypeEnum::Email, ContactTypeEnum::Pec])
            ->makeVisible('value')
            ->map(fn (Contact $contact): array => [
                'email' => (string) $contact->value,
                'label' => "{$ownerName} ({$contact->type->label()})",
                'source' => $source,
            ])
            ->all();
    }

    /**
     * Adds $suggestions to $byEmail keeping the first occurrence of each
     * address (case-insensitive).
     *
     * @param  array<string, array{email: string, label: string, source: string}>  $byEmail
     * @param  array<int, array{email: string, label: string, source: string}>  $suggestions
     */
    public function merge(array &$byEmail, array $suggestions): void
    {
        foreach ($suggestions as $suggestion) {
            $byEmail[mb_strtolower($suggestion['email'])] ??= $suggestion;
        }
    }
}
