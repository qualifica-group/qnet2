<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Referent;
use App\Models\Registry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Row projection for the `rewarded-referents` domain (spec 0059): turns an
 * eager-loaded Referent (baseQuery: `personalData.contacts`, `registries`,
 * plus the `rewards_count`/`pending_rewards_count`/`approved_rewards_count`/
 * `last_assigned_at` aggregate aliases) into the SSRM row payload — every
 * value resolved from memory, never a fresh query (AC-013).
 *
 * Split out of RewardedReferentsTableDefinition (file-size split,
 * engineering.md §6), mirroring RequestRowMapper's single concern
 * (query building stays on the definition; presentation lives here).
 */
final class RewardedReferentRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(Referent $row): array
    {
        $card = $row->personalData;
        $contacts = $card?->contacts;

        return [
            'id' => $row->id,
            'name' => $row->name,
            'full_name' => $card?->full_name,
            'registries' => $this->summarizeRegistries($row->registries),
            'email' => $this->primaryValue($contacts, ContactTypeEnum::Email),
            'phone' => $this->primaryValue($contacts, ContactTypeEnum::Phone)
                ?? $this->primaryValue($contacts, ContactTypeEnum::Mobile),
            'rewards_count' => (int) ($row->rewards_count ?? 0),
            'pending_rewards_count' => (int) ($row->pending_rewards_count ?? 0),
            'approved_rewards_count' => (int) ($row->approved_rewards_count ?? 0),
            'last_assigned_at' => $this->isoDate($row->last_assigned_at),
        ];
    }

    /**
     * Comma-joined names of the referent's linked registries (D-6's
     * "anagrafiche collegate", `referent_registry` pivot) — null when there
     * is none, mirroring RequestRowMapper::summarizeNames.
     *
     * @param  Collection<int, Registry>  $registries
     */
    private function summarizeRegistries(Collection $registries): ?string
    {
        $names = $registries->pluck('name')->filter()->unique()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * The card's primary contact VALUE of $type, read from the
     * already-eager-loaded in-memory collection — never the model's own
     * `primaryContact()` helper (that issues a fresh query, AC-013).
     *
     * @param  Collection<int, Contact>|null  $contacts
     */
    private function primaryValue(?Collection $contacts, ContactTypeEnum $type): ?string
    {
        $contact = $contacts?->first(
            static fn (Contact $contact): bool => $contact->is_primary && $contact->type === $type,
        );

        return $contact?->value;
    }

    /**
     * `last_assigned_at` is a raw `withMax` SELECT-list alias, never routed
     * through Reward's own `assigned_at` cast: on SQLite (no native DATE
     * type) the stored/aggregated value carries a `00:00:00` time part, so
     * the wire contract's plain ISO date (`data_contract`) is normalized
     * here rather than assumed already clean.
     */
    private function isoDate(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->toDateString() : null;
    }
}
