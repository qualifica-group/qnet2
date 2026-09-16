<?php

namespace App\Tables\Users;

use App\Enums\PersonalDataTypeEnum;
use App\Models\Address;
use App\Models\PersonalData;
use App\Models\User;
use App\Tables\Concerns\HandlesBlankSetFilter;
use App\Tables\Shared\PrimaryContactColumn;
use App\Tables\Users\Concerns\CorrelatesPersonalDataToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The personal-data-derived columns on the `users` table with no real DB
 * column of their own: `user_type` (badge, from personalData.type),
 * `primary_address` (formatted line from the primary Address) and
 * `primary_contact` (all primary Contacts, one per type).
 *
 * Extracted out of UsersTableDefinition (file-size split, engineering.md §6):
 * row formatting, badge/enum metadata, the derived filters/sorts and the
 * Excel-like distinct-values resolution for `user_type` all live in one
 * focused file. The `primary_contact` mechanics are delegated to the shared
 * PrimaryContactColumn (reused verbatim by the referents domain) — this class
 * only binds them to the `users` owner. Behavior is unchanged.
 */
class UserPersonalDataColumns
{
    use CorrelatesPersonalDataToUser;
    use HandlesBlankSetFilter;

    /**
     * Maximum number of values honoured in the `user_type` set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly PrimaryContactColumn $contactColumn) {}

    /**
     * Plain string[] of the PersonalDataTypeEnum values, used both as the
     * `user_type` set-filter/distinct-values options and (via typeBadges) the
     * badge value tokens.
     *
     * @return array<int, string>
     */
    public function typeValues(): array
    {
        return array_map(
            static fn (PersonalDataTypeEnum $case): string => $case->value,
            PersonalDataTypeEnum::cases(),
        );
    }

    /**
     * Badge metadata for the `user_type` column: value/label/color/icon for
     * each PersonalDataTypeEnum case, so the frontend renders the badge
     * without any knowledge of the User domain.
     *
     * @return array<int, array<string, mixed>>
     */
    public function typeBadges(): array
    {
        return array_map(
            static fn ($meta): array => $meta->toArray(),
            PersonalDataTypeEnum::options(),
        );
    }

    /**
     * Excel-like distinct values (spec 0004) for `user_type`: the same
     * catalogue, optionally narrowed by a case-insensitive substring search
     * and capped to `$limit`.
     *
     * @return array<int, string>
     */
    public function distinctTypeValues(?string $search, int $limit): array
    {
        $values = $this->typeValues();

        if ($search !== null && $search !== '') {
            return array_slice(array_values(array_filter(
                $values,
                static fn (string $value): bool => stripos($value, $search) !== false,
            )), 0, $limit);
        }

        // The blank entry ("(Vuoti)") sits beside the catalogue (itself
        // offered unscoped): a user may own no personal-data card at all.
        return array_merge([null], array_slice($values, 0, $limit));
    }

    /**
     * Row fields derived from the personalData card + its primary address.
     * The raw sensitive fields (line1, contact value) are read here ONLY to
     * build the formatted strings; they are never returned as row fields.
     *
     * @return array{user_type: string|null, primary_address: string|null, primary_contact: array<int, array<string, mixed>>}
     */
    public function mapRow(?PersonalData $card, ?Address $address): array
    {
        return [
            'user_type' => $card?->type?->value,
            'primary_address' => $this->formatAddress($address),
            'primary_contact' => $this->contactColumn->format($card?->contacts),
        ];
    }

    /**
     * Derived `user_type` set filter on personalData.type. Only valid enum
     * values are honoured; whereHas implicitly excludes users without a card.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyTypeFilter(Builder $query, array $filter): void
    {
        $values = array_values(array_filter(
            $this->setFilterValues($filter),
            static fn (string $value): bool => PersonalDataTypeEnum::tryFrom($value) !== null,
        ));

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas('personalData', static function (Builder $cardQuery) use ($values): void {
                    $cardQuery->whereIn('type', $values);
                });
            }

            // The blank entry ("(Vuoti)"): no card, or a card with no type.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('personalData', static function (Builder $cardQuery): void {
                    $cardQuery->whereNotNull('type');
                });
            }
        });
    }

    /**
     * Derived `primary_address` text filter: bound LIKE on the primary
     * address' street/postal/city-name. Wildcards in user input are escaped.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyAddressFilter(Builder $query, array $filter): void
    {
        $needle = $this->likeNeedle($filter);

        if ($needle !== null) {
            $query->whereHas('personalData.addresses', function (Builder $addressQuery) use ($needle): void {
                $addressQuery->where('is_primary', true)
                    ->where(function (Builder $match) use ($needle): void {
                        $match->where('line1', 'like', $needle)
                            ->orWhere('postal_code', 'like', $needle)
                            ->orWhereHas('city', static function (Builder $cityQuery) use ($needle): void {
                                $cityQuery->where('name', 'like', $needle);
                            });
                    });
            });
        }
    }

    /**
     * Derived `primary_contact` text filter, delegated to the shared column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyContactFilter(Builder $query, array $filter): void
    {
        $this->contactColumn->applyTextFilter($query, $filter);
    }

    /**
     * Derived `primary_contact` SET filter, delegated to the shared column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyContactSetFilter(Builder $query, array $filter): void
    {
        $this->contactColumn->applySetFilter($query, $filter);
    }

    /**
     * @return Builder<Model>
     */
    public function typeSortSubquery(): Builder
    {
        return $this->correlateToUser(
            PersonalData::query()->select('personal_data.type'),
        );
    }

    /**
     * @return Builder<Model>
     */
    public function addressSortSubquery(): Builder
    {
        return $this->correlateToUser(
            Address::query()
                ->select('addresses.line1')
                ->join('personal_data', 'personal_data.id', '=', 'addresses.addressable_id')
                ->where('addresses.addressable_type', (new PersonalData)->getMorphClass())
                ->where('addresses.is_primary', true),
        );
    }

    /**
     * @return Builder<Model>
     */
    public function contactSortSubquery(): Builder
    {
        return $this->contactColumn->sortSubquery('users', (new User)->getMorphClass());
    }

    /**
     * Excel-like distinct values for the COMPUTED `primary_contact` column,
     * delegated to the shared column and bound to the `users` owner.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function contactDistinctValues(Builder $query, ?string $search, int $limit): array
    {
        return $this->contactColumn->distinctValues($query, 'users', (new User)->getMorphClass(), $search, $limit);
    }

    /**
     * Format the primary address as a single human-readable line, e.g.
     * "Via Roma 12, 20100 Milano (MI)". Returns null when there is no address.
     */
    private function formatAddress(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $street = trim((string) ($address->line1 ?? ''));
        $locality = trim(implode(' ', array_filter([
            $address->postal_code,
            $address->city?->localizedName(),
        ])));
        $province = $address->province?->localizedName();

        if ($province !== null && $locality !== '') {
            $locality .= " ({$province})";
        }

        $line = trim(implode(', ', array_filter([$street ?: null, $locality ?: null])));

        return $line === '' ? null : $line;
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
