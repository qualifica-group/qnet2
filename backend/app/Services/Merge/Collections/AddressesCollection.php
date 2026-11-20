<?php

namespace App\Services\Merge\Collections;

use App\Models\Address;
use App\Services\Merge\Collections\Concerns\ResolvesCards;
use App\Services\Merge\MergeItem;
use App\Services\Merge\MergeItemDeduper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Addresses of the records' cards. Same line1 + postal code + city = same
 * address (spec 0394, D-6); a moved primary loses the flag when the survivor
 * already has one.
 */
final class AddressesCollection implements MergeCollection
{
    use ResolvesCards;

    public function key(): string
    {
        return 'addresses';
    }

    public function items(Collection $records): array
    {
        $owners = $this->cardOwners($records);

        return Address::query()
            ->with('city:id,name')
            ->where('addressable_type', 'personal_data')
            ->whereIn('addressable_id', array_keys($owners))
            ->orderBy('id')
            ->get()
            ->map(static fn (Address $address): MergeItem => new MergeItem(
                id: $address->id,
                recordId: $owners[$address->addressable_id],
                label: (string) $address->line1,
                detail: trim(implode(' ', array_filter([$address->postal_code, $address->city?->name]))) ?: null,
                matchKey: self::matchKey($address),
            ))
            ->sortBy('recordId')
            ->values()
            ->all();
    }

    private static function matchKey(Address $address): string
    {
        $line = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $address->line1)));

        return $line.'|'.mb_strtolower(trim((string) $address->postal_code)).'|'.($address->city_id ?? '');
    }

    public function apply(Model $survivor, Collection $merged, array $discardedIds): void
    {
        $survivorCard = $this->survivorCardId($survivor, $merged);
        $items = $this->items(collect([$survivor])->concat($merged));
        $hasPrimary = Address::query()
            ->where('addressable_type', 'personal_data')
            ->where('addressable_id', $survivorCard)
            ->where('is_primary', true)
            ->exists();

        foreach (MergeItemDeduper::toMove($items, (int) $survivor->getKey(), $discardedIds) as $item) {
            $wasPrimary = (bool) DB::table('addresses')->where('id', $item->id)->value('is_primary');
            $keepsPrimary = $wasPrimary && ! $hasPrimary;
            $hasPrimary = $hasPrimary || $keepsPrimary;

            DB::table('addresses')->where('id', $item->id)->update(['addressable_id' => $survivorCard, 'is_primary' => $keepsPrimary]);
        }
    }
}
