<?php

namespace App\Tables\Users;

use App\Models\Address;
use App\Models\PersonalData;
use App\Models\User;
use App\Tables\Shared\PersonalDataTypeColumn;
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
 * focused file. The `user_type` and `primary_contact` mechanics are delegated
 * to the shared PersonalDataTypeColumn / PrimaryContactColumn (reused verbatim
 * by the registries / referents domains) — this class only binds them to the
 * `users` owner. Behavior is unchanged.
 */
class UserPersonalDataColumns
{
    use CorrelatesPersonalDataToUser;

    public function __construct(
        private readonly PrimaryContactColumn $contactColumn,
        private readonly PersonalDataTypeColumn $typeColumn,
    ) {}

    /**
     * @return array<int, string>
     */
    public function typeValues(): array
    {
        return $this->typeColumn->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function typeBadges(): array
    {
        return $this->typeColumn->badges();
    }

    /**
     * @return array<int, string|null>
     */
    public function distinctTypeValues(?string $search, int $limit): array
    {
        return $this->typeColumn->distinctValues($search, $limit);
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
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyTypeFilter(Builder $query, array $filter): void
    {
        $this->typeColumn->applyFilter($query, $filter);
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
        return $this->typeColumn->sortSubquery('users', (new User)->getMorphClass());
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
