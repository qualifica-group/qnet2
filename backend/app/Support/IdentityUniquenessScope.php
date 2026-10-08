<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The entities whose personal-data cards share ONE identity namespace (user
 * directive 2026-08-06): a codice fiscale, a partita IVA or a phone number
 * already held by a USER, by an ANAGRAFICA or by a REFERENTE blocks the
 * creation of another anagrafica or referente carrying it.
 *
 * Supersedes the per-module scope of 2026-08-03, under which each module was
 * checked only against itself and the user accounts were never consulted.
 *
 * `CompanySite` also owns cards through the `personable` morph and is
 * deliberately OUT: a site is a location of a company that its anagrafica
 * already represents, so its switchboard number is not a second person.
 *
 * The scope is a single source of truth for every surface that enforces the
 * constraint — `UniquePersonalDataIdentifier` (fiscal columns),
 * `ValidatesPhoneUniqueness` (contact rows), the contact endpoints and the
 * request-management client writer — and for the live duplicate panel
 * (`IdentityDuplicateFinder`), so none of them can drift apart.
 */
final class IdentityUniquenessScope
{
    /**
     * The fiscal identifier columns of a card. The column name is interpolated
     * into `whereRaw` (the value stays bound), so it may only ever come from
     * this allow-list, never from request input (backend.md §8).
     *
     * @var array<int, string>
     */
    public const array FISCAL_COLUMNS = ['tax_code', 'vat_number'];

    /** @var array<int, class-string<Model>> */
    private const array OWNERS = [User::class, Registry::class, Referent::class];

    /**
     * The cards inside the namespace, minus the one owned by the record under
     * edit — keeping its own values must stay a no-op, not a self-collision.
     *
     * The owner is excluded as a (type, id) PAIR, never by id alone: the three
     * morphs have independent primary keys, so referent #5 and registry #5 both
     * exist and dropping "personable_id = 5" would blind the check to the other.
     *
     * @param  class-string<Model>|null  $ignoreOwnerClass
     * @return Builder<PersonalData>
     */
    public static function cards(?string $ignoreOwnerClass = null, ?int $ignoreOwnerId = null): Builder
    {
        return PersonalData::query()
            ->whereIn('personable_type', self::morphClasses())
            ->when(
                $ignoreOwnerClass !== null && $ignoreOwnerId !== null,
                fn (Builder $query) => $query->whereNot(
                    fn (Builder $owned) => $owned
                        ->where('personable_type', self::morphClass($ignoreOwnerClass))
                        ->where('personable_id', $ignoreOwnerId),
                ),
            );
    }

    /**
     * Whether a card owned by $personableType (a morph alias) belongs to the
     * namespace at all — a company-site card does not, and is never checked.
     */
    public static function covers(?string $personableType): bool
    {
        return in_array($personableType, self::morphClasses(), true);
    }

    /**
     * Narrows $cards to those holding $normalized in ANY fiscal column (user
     * directive 2026-10-08): a company's codice fiscale is usually its partita
     * IVA, so the value one card stores as CF and another as P.IVA is the same
     * company. Matching column-to-column only would let that pair through.
     *
     * @param  Builder<PersonalData>  $cards  a `cards()` query
     * @param  string  $normalized  already passed through ContactValueNormalizer::taxCode
     * @return Builder<PersonalData>
     */
    public static function withFiscalIdentifier(Builder $cards, string $normalized): Builder
    {
        return $cards->where(function (Builder $query) use ($normalized): void {
            foreach (self::FISCAL_COLUMNS as $column) {
                $query->orWhereRaw("UPPER(TRIM({$column})) = ?", [$normalized]);
            }
        });
    }

    /**
     * Whether one of $cards already carries the phone number $normalized,
     * through the indexed `normalized_value` column (spec 0136 D-6) — an index
     * lookup, never a scan of every phone row in the namespace.
     *
     * @param  Builder<PersonalData>  $cards  a `cards()` query
     * @param  string  $normalized  already passed through ContactValueNormalizer::contact
     */
    public static function phoneTaken(Builder $cards, string $normalized): bool
    {
        return Contact::query()
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->where('type', ContactTypeEnum::Phone->value)
            ->where('normalized_value', $normalized)
            ->whereIn('contactable_id', $cards->select('id'))
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private static function morphClasses(): array
    {
        return array_map(self::morphClass(...), self::OWNERS);
    }

    /**
     * @param  class-string<Model>  $ownerClass
     */
    private static function morphClass(string $ownerClass): string
    {
        /** @var Model $owner */
        $owner = new $ownerClass;

        return $owner->getMorphClass();
    }
}
