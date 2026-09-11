<?php

namespace App\Tables\Shared;

use App\Models\Contact;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The COMPUTED `primary_contact` table column, shared by every domain whose
 * model owns a personal-data card (`HasPersonalData` morph) — Users and
 * Referents today. Encapsulates the whole column contract identically for
 * both: the row payload (all primary contacts, one per type), the text/set
 * derived filters (whereHas on `personalData.contacts`), the correlated sort
 * scalar and the Excel-like distinct values.
 *
 * The two owner bindings (the owner table name and its morph alias) are the
 * ONLY parameters that differ between domains; every other rule — is_primary
 * scoping, value/label search, bound LIKE, capped cardinality — is
 * domain-agnostic and lives here once, so the two columns can never drift.
 */
final class PrimaryContactColumn
{
    /**
     * Maximum number of values honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Structured payload for EVERY primary contact (one per type), so the
     * frontend renders each as a badge with the contact-type icon + label
     * without knowing the ContactType domain. Empty array when there is none
     * (the collection is already constrained to is_primary by the base query).
     *
     * @param  Collection<int, Contact>|null  $contacts
     * @return array<int, array{type: string, icon: string|null, label: string, value: string}>
     */
    public function format(?Collection $contacts): array
    {
        if ($contacts === null) {
            return [];
        }

        return $contacts
            ->map(fn (Contact $contact): ?array => $this->formatContact($contact))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The PRIMARY contacts of a record owning a personal-data card
     * (`HasPersonalData`), already projected — the same shape format()
     * returns. Reads the eager-loaded relation only: the caller is
     * responsible for having loaded `personalData.contacts`.
     *
     * @return array<int, array{type: string, icon: string|null, label: string, value: string}>
     */
    public function formatFor(?Model $owner): array
    {
        if ($owner === null) {
            return [];
        }

        /** @var PersonalData|null $card */
        $card = $owner->personalData;

        return $card === null
            ? []
            : $this->format($card->contacts->where('is_primary', true)->values());
    }

    /**
     * Derived text filter: bound LIKE on the primary contact's value/label.
     * Wildcards in user input are escaped.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyTextFilter(Builder $query, array $filter): void
    {
        $needle = $this->likeNeedle($filter);

        if ($needle !== null) {
            $query->whereHas('personalData.contacts', function (Builder $contactQuery) use ($needle): void {
                $contactQuery->where('is_primary', true)
                    ->where(function (Builder $match) use ($needle): void {
                        $match->where('value', 'like', $needle)
                            ->orWhere('label', 'like', $needle);
                    });
            });
        }
    }

    /**
     * Derived SET filter (spec 0004/0005 `multi` widget): matches the real
     * contact VALUE — the same string the /values endpoint returns — of the
     * PRIMARY contact. Bound, capped cardinality.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applySetFilter(Builder $query, array $filter): void
    {
        $values = $this->setFilterValues($filter);

        if ($values !== []) {
            $query->whereHas('personalData.contacts', static function (Builder $contactQuery) use ($values): void {
                $contactQuery->where('is_primary', true)->whereIn('value', $values);
            });
        }
    }

    /**
     * ORDER BY scalar: the smallest primary-contact value of the owner row,
     * via a correlated subquery (never a row-multiplying JOIN on the main
     * query). `$ownerTable`/`$ownerMorphClass` bind it to the concrete owner
     * (users / referents).
     *
     * @return Builder<Model>
     */
    public function sortSubquery(string $ownerTable, string $ownerMorphClass): Builder
    {
        return Contact::query()
            ->selectRaw('min(contacts.value)')
            ->join('personal_data', 'personal_data.id', '=', 'contacts.contactable_id')
            ->where('contacts.contactable_type', (new PersonalData)->getMorphClass())
            ->where('contacts.is_primary', true)
            ->whereColumn('personal_data.personable_id', "{$ownerTable}.id")
            ->where('personal_data.personable_type', $ownerMorphClass)
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): distinct `value` of the
     * PRIMARY contacts of every owner matching `$query` (already scoped by
     * every OTHER active filter). Search narrows on value/label, bound +
     * LIKE-escaped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, string $ownerTable, string $ownerMorphClass, ?string $search, int $limit): array
    {
        $ownerIds = (clone $query)->select("{$ownerTable}.id");

        $cardIds = DB::table('personal_data')
            ->select('id')
            ->where('personable_type', $ownerMorphClass)
            ->whereIn('personable_id', $ownerIds);

        return DB::table('contacts')
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->where('is_primary', true)
            ->whereIn('contactable_id', $cardIds)
            ->whereNotNull('value')
            ->when($search !== null && $search !== '', function (QueryBuilder $q) use ($search): void {
                $needle = '%'.$this->escapeLike($search).'%';
                $q->where(function (QueryBuilder $sub) use ($needle): void {
                    $sub->where('value', 'like', $needle)
                        ->orWhere('label', 'like', $needle);
                });
            })
            ->distinct()
            ->orderBy('value')
            ->limit($limit)
            ->pluck('value')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Structured payload for a single contact: its type, the type's icon (from
     * the enum metadata), a display label (the contact's own label when set,
     * else the type label) and the value. Returns null when the value is empty.
     *
     * @return array{type: string, icon: string|null, label: string, value: string}|null
     */
    private function formatContact(Contact $contact): ?array
    {
        $value = trim((string) ($contact->value ?? ''));

        if ($value === '') {
            return null;
        }

        $type = $contact->type;
        $label = trim((string) ($contact->label ?? ''));

        return [
            'type' => $type->value,
            'icon' => $type->icon(),
            'label' => $label !== '' ? $label : $type->label(),
            'value' => $value,
        ];
    }

    /**
     * Extract, sanitize and cap the string values of a set filter payload.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $clean = array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        return array_slice($clean, 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Build a bound `%needle%` LIKE pattern from a text filter, or null when
     * the filter carries no usable value. Wildcards are escaped so they are
     * literal.
     *
     * @param  array<string, mixed>  $filter
     */
    private function likeNeedle(array $filter): ?string
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return '%'.$this->escapeLike((string) $value).'%';
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
