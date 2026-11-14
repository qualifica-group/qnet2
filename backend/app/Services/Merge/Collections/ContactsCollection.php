<?php

namespace App\Services\Merge\Collections;

use App\Models\Contact;
use App\Services\Merge\Collections\Concerns\ResolvesCards;
use App\Services\Merge\MergeItem;
use App\Services\Merge\MergeItemDeduper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contacts of the records' cards. Same `type` + `normalized_value` = same
 * contact (spec 0394, D-6); a moved primary loses the flag when the survivor
 * already has a primary of that type.
 */
final class ContactsCollection implements MergeCollection
{
    use ResolvesCards;

    public function key(): string
    {
        return 'contacts';
    }

    public function items(Collection $records): array
    {
        $owners = $this->cardOwners($records);

        return Contact::query()
            ->where('contactable_type', 'personal_data')
            ->whereIn('contactable_id', array_keys($owners))
            ->orderBy('id')
            ->get()
            ->map(static fn (Contact $contact): MergeItem => new MergeItem(
                id: $contact->id,
                recordId: $owners[$contact->contactable_id],
                label: (string) $contact->value,
                detail: $contact->type->value,
                matchKey: $contact->type->value.'|'.($contact->normalized_value ?? mb_strtolower(trim((string) $contact->value))),
            ))
            ->sortBy('recordId')
            ->values()
            ->all();
    }

    public function apply(Model $survivor, Collection $merged, array $discardedIds): void
    {
        $survivorCard = $this->survivorCardId($survivor, $merged);
        $items = $this->items(collect([$survivor])->concat($merged));
        $primaryTypes = Contact::query()
            ->where('contactable_type', 'personal_data')
            ->where('contactable_id', $survivorCard)
            ->where('is_primary', true)
            ->get()
            ->map(static fn (Contact $contact): string => $contact->type->value)
            ->all();

        foreach (MergeItemDeduper::toMove($items, (int) $survivor->getKey(), $discardedIds) as $item) {
            $row = DB::table('contacts')->where('id', $item->id)->first(['type', 'is_primary']);
            $keepsPrimary = (bool) $row->is_primary && ! in_array($row->type, $primaryTypes, true);

            if ($keepsPrimary) {
                $primaryTypes[] = $row->type;
            }

            DB::table('contacts')->where('id', $item->id)->update(['contactable_id' => $survivorCard, 'is_primary' => $keepsPrimary]);
        }
    }
}
