<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ProfileData;
use App\Enums\ReferentContactScopeEnum;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\User;
use App\Services\ReferentService;
use Spatie\Activitylog\Models\Activity;

/**
 * "Referente 1" and "Referente 2" of a legacy company on its anagrafica (spec 0398),
 * shared by the import and the one-off command: each is matched to the referent the
 * legacy names (`contact_id` -> `old_id`) or to a linked referent with the same name,
 * created otherwise, and linked. Then the principale follows D-9, unless it was changed
 * by hand. The caller runs it with the activity log off.
 */
final class RegistryResponsibleReferents
{
    public const string REASON_RESPONSIBLE = 'referente_1';

    public const string REASON_COMMERCIAL = 'commerciale';

    private const string FIELD = 'responsible_referents';

    private const int PRINCIPAL_SLOT = 1;

    private const array SLOTS = [1, 2];

    private const string IMPORT_EVENT = 'created';

    public function __construct(
        private readonly ReferentService $referents,
        private readonly ResponsibleReferentProfile $profiles,
    ) {}

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array{links: list<array{slot: int, referent: Referent, created: bool, contacts_added: int}>, primary: ?array{old: ?Referent, new: ?Referent, reason: string}, kept_manual: bool}
     */
    public function align(Registry $registry, array $legacy, User $actor, array &$warnings): array
    {
        // Step 1: a legacy without the spec 0398 fields changes nothing (AC-014).
        if (! array_key_exists(self::FIELD, $legacy)) {
            return ['links' => [], 'primary' => null, 'kept_manual' => false];
        }

        // Step 2: Referente 1 and 2, matched or created, and linked.
        $links = [];

        foreach ((array) $legacy[self::FIELD] as $entry) {
            $link = $this->link($registry, (array) $entry, $actor, $warnings);

            if ($link !== null) {
                $links[] = $link;
            }
        }

        // Step 3: the principale, unless changed by hand.
        $responsibleOne = collect($links)->firstWhere('slot', self::PRINCIPAL_SLOT)['referent'] ?? null;
        [$primary, $keptManual] = $this->alignPrimary($registry, $legacy, $responsibleOne);

        return ['links' => $links, 'primary' => $primary, 'kept_manual' => $keptManual];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $warnings
     * @return array{slot: int, referent: Referent, created: bool, contacts_added: int}|null
     */
    private function link(Registry $registry, array $entry, User $actor, array &$warnings): ?array
    {
        $slot = (int) ($entry['slot'] ?? 0);
        $profile = in_array($slot, self::SLOTS, true) ? $this->profiles->profile($entry, $warnings) : null;

        if ($profile === null) {
            $warnings[] = "Referente {$slot} without a slot 1/2 or a first name, skipped.";

            return null;
        }

        $referent = $this->existingReferent($registry, $entry, $profile, $warnings);
        $created = $referent === null;
        $contactsAdded = count($profile->contacts ?? []);

        if ($referent === null) {
            $referent = $this->referents->create($actor, $this->referentData($entry, $warnings), $profile);
        } else {
            $contactsAdded = $this->addMissingContacts($referent, $profile);
        }

        $registry->referents()->syncWithoutDetaching([$referent->id]);

        return ['slot' => $slot, 'referent' => $referent, 'created' => $created, 'contacts_added' => $contactsAdded];
    }

    /**
     * The referent the legacy names, else a referent already linked with the same name
     * (a re-run, D-10).
     *
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $warnings
     */
    private function existingReferent(Registry $registry, array $entry, ProfileData $profile, array &$warnings): ?Referent
    {
        $contactId = $entry['contact_id'] ?? null;

        if ($contactId !== null && $contactId !== '') {
            $referent = Referent::query()->where('old_id', $contactId)->first();

            if ($referent !== null) {
                return $referent;
            }

            $warnings[] = "Referente {$entry['slot']}: legacy contact {$contactId} not migrated, matched by name.";
        }

        $name = $this->comparableName($profile->card->displayName());

        return $registry->referents()->get()
            ->first(fn (Referent $referent): bool => $this->comparableName((string) $referent->name) === $name);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $warnings
     */
    private function referentData(array $entry, array &$warnings): CreateReferentData
    {
        $legacyTypeId = $entry['referent_type_id'] ?? null;
        $typeId = $legacyTypeId !== null ? ReferentType::query()->where('old_id', $legacyTypeId)->value('id') : null;

        if ($legacyTypeId !== null && $typeId === null) {
            $warnings[] = "Unresolved referent_type_id (external id {$legacyTypeId}).";
        }

        return new CreateReferentData(
            referentTypeId: $typeId !== null ? (int) $typeId : null,
            contactScope: ReferentContactScopeEnum::External->value,
            notes: null,
        );
    }

    private function addMissingContacts(Referent $referent, ProfileData $profile): int
    {
        $card = $referent->personalData()->with('contacts')->first();

        if ($card === null) {
            return 0;
        }

        $missing = MissingCardContacts::of($card, $profile->contacts ?? []);

        foreach ($missing as $attributes) {
            $card->contacts()->create($attributes);
        }

        return count($missing);
    }

    /**
     * D-9: Referente 1 first; else a Commerciale principale gives way to the first
     * linked referent that is not one, or to none.
     *
     * @param  array<string, mixed>  $legacy
     * @return array{0: ?array{old: ?Referent, new: ?Referent, reason: string}, 1: bool}
     */
    private function alignPrimary(Registry $registry, array $legacy, ?Referent $responsibleOne): array
    {
        $currentId = $registry->primary_referent_id !== null ? (int) $registry->primary_referent_id : null;
        $target = $this->targetPrimary($registry, $legacy, $responsibleOne, $currentId);

        if ($target === null || $target[0] === $currentId) {
            return [null, false];
        }

        if ($this->primaryChangedByHand($registry)) {
            return [null, true];
        }

        [$newId, $reason] = $target;
        $old = $currentId !== null ? Referent::query()->find($currentId) : null;
        $registry->forceFill(['primary_referent_id' => $newId])->save();

        return [['old' => $old, 'new' => $newId !== null ? Referent::query()->find($newId) : null, 'reason' => $reason], false];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array{0: ?int, 1: string}|null null when the principale stays
     */
    private function targetPrimary(Registry $registry, array $legacy, ?Referent $responsibleOne, ?int $currentId): ?array
    {
        if ($responsibleOne !== null) {
            return [(int) $responsibleOne->id, self::REASON_RESPONSIBLE];
        }

        $commercialIds = $this->commercialIds($registry, $legacy);

        if ($currentId === null || ! in_array($currentId, $commercialIds, true)) {
            return null;
        }

        $firstOther = $registry->referents()->pluck('referents.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->first(static fn (int $id): bool => ! in_array($id, $commercialIds, true));

        return [$firstOther, self::REASON_COMMERCIAL];
    }

    /**
     * The Commerciali of the anagrafica: the legacy "Commerciale" contacts and its own
     * commercial (D-8).
     *
     * @param  array<string, mixed>  $legacy
     * @return list<int>
     */
    private function commercialIds(Registry $registry, array $legacy): array
    {
        $legacyIds = array_values(array_filter((array) ($legacy['commercial_contact_ids'] ?? []), static fn (mixed $id): bool => $id !== null && $id !== ''));
        $ids = $legacyIds === [] ? [] : Referent::query()->whereIn('old_id', $legacyIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($registry->commercial_id !== null) {
            $ids[] = (int) $registry->commercial_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The import runs without activity log, so any logged change of the principale was
     * made by a person (spec 0398 D-9).
     */
    private function primaryChangedByHand(Registry $registry): bool
    {
        return Activity::query()
            ->where('subject_type', $registry->getMorphClass())
            ->where('subject_id', $registry->id)
            ->where(static fn ($query) => $query->whereNull('event')->orWhere('event', '<>', self::IMPORT_EVENT))
            ->whereJsonContainsKey('properties->attributes->primary_referent_id')
            ->exists();
    }

    private function comparableName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }
}
