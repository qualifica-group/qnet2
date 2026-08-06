<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use App\Tables\Shared\ProductsOfInterestColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Row projection for the `request-management` domain (spec 0049): turns an
 * eager-loaded Opportunity into the operative row payload the grid renders.
 *
 * Split out of RequestManagementTableDefinition so the definition keeps a
 * single concern (query building: scoping, filters, sorts, distinct values)
 * and the per-row presentation lives here. Every value is resolved from
 * relations already loaded by the definition's baseQuery — this mapper never
 * queries.
 *
 * Spec 0083: the working-state columns (`workflow_status` and the per-row
 * `workflow_status_options`) are gone from this domain — the operational
 * status now lives on the Offerta, not on the Opportunita'. With them went the
 * only reason this mapper had a constructor dependency at all.
 */
final class RequestRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(Opportunity $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            // "Fonte" (user directive 2026-07-31): the `{id, name}` ref both
            // the relation cell and its inline picker read.
            'source' => $this->summarize($row->source),
            // "Richieste di modifica in attesa" (spec 0078, AC-037): rides
            // along from baseQuery's withCount('pendingFieldChangeRequests')
            // — already excludes approved/rejected requests (D-4/D-5), so no
            // further filtering is needed here. 0 when the relation was not
            // counted (defensive default, never expected on this domain's
            // own baseQuery).
            'pending_change_requests' => (int) ($row->pending_field_change_requests_count ?? 0),
            // "Note generali" (user directive 2026-07-31): the opportunity's
            // own free text, projected raw — display-only in this module.
            'general_notes' => $row->general_notes,
            // "Categoria prodotto": the request's own product lines (spec
            // 0075). Projected as the {funzione aziendale, categoria} PAIRS —
            // ids for the inline editor to commit, names for the cell to
            // render — and no longer as a pre-joined string: the cell is
            // edited through the same collection the form edits, so its value
            // must BE that collection.
            'product_categories' => $this->productLinePairs($row),
            // "Prodotti di interesse" (user directive 2026-07-23): the same
            // projection the opportunities grid emits — the selected `{id,
            // name}` refs plus the category ids the inline editor scopes to.
            ...ProductsOfInterestColumn::project($row),
            // "Operatore": the Account Manager at pivot position 2 (GA2).
            'operator_ga2' => $this->operatorSummary($row->managers),
            // Spec 0056: the Sede operativa — the site has no own name, so
            // its label is composed server-side from its primary address.
            'operational_site' => OperationalSiteLabel::summarize($row->operationalSite),
            // "Trasferito" (spec 0079): a real column, never derived —
            // display-only, no inline editor exists for it (AC-024).
            'is_transferred' => (bool) $row->is_transferred,
            ...$this->clientAnagraphics($row),
            // "Prossimo richiamo" (spec 0052 D-1/D-5), same wire format as
            // RequestManagementResource so FE date parsing stays identical.
            'next_callback_at' => $row->next_callback_at?->format('Y-m-d\TH:i'),
            // Hidden column, drives the default "most recently loaded first" sort only.
            'created_at' => $row->created_at,
        ];
    }

    /**
     * The client anagraphic columns, read from the Registry's PersonalData
     * card (phone = its primary phone/mobile contact).
     *
     * @return array<string, string|null>
     */
    private function clientAnagraphics(Opportunity $row): array
    {
        $card = $row->registry?->personalData;

        return [
            'first_name' => $card?->first_name,
            'last_name' => $card?->last_name,
            'tax_code' => $card?->tax_code,
            'phone' => $this->primaryPhone($card?->contacts),
        ];
    }

    /**
     * The GA2 operator as a person summary (id, name, inline avatar) for the
     * shared UserCell — the Account Manager attached at pivot `position` =
     * Opportunity::OPERATOR_MANAGER_POSITION, or null when that slot is empty.
     * Mirrors OpportunitiesTableDefinition::userSummary (supervisor column).
     *
     * @param  Collection<int, User>  $managers
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function operatorSummary(Collection $managers): ?array
    {
        $operator = $managers->first(
            static fn (User $manager): bool => (int) $manager->pivot->position === Opportunity::OPERATOR_MANAGER_POSITION,
        );

        if ($operator === null) {
            return null;
        }

        return ['id' => $operator->id, 'name' => $operator->name, 'avatar_url' => $operator->avatarDataUri()];
    }

    /**
     * The client's primary phone number: the first primary contact of a
     * telephone kind (phone or mobile) on the card, or null.
     *
     * @param  Collection<int, Contact>|null  $contacts
     */
    private function primaryPhone(?Collection $contacts): ?string
    {
        $phone = $contacts?->first(static fn (Contact $contact): bool => $contact->is_primary
            && in_array($contact->type, [ContactTypeEnum::Phone, ContactTypeEnum::Mobile], true));

        return $phone?->value;
    }

    /**
     * A related row projected as the plain `{id, name}` ref the shared
     * RelationCell renders and the inline relation editor pre-selects.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * The `product_categories` column's value (spec 0075): one entry per
     * persisted product line, carrying BOTH ids (what the inline editor
     * commits, and what PATCH /rows expects) and both names (what the cell
     * renders, and what the editor labels its chips with). A line whose
     * relation is missing is skipped rather than projected half-empty.
     *
     * @return array<int, array{business_function_id: int, business_function_name: string, product_category_id: int, product_category_name: string}>
     */
    private function productLinePairs(Opportunity $row): array
    {
        return $row->productLines
            ->filter(static fn ($line): bool => $line->businessFunction !== null && $line->productCategory !== null)
            ->map(static fn ($line): array => [
                'business_function_id' => (int) $line->business_function_id,
                'business_function_name' => (string) $line->businessFunction->name,
                'product_category_id' => (int) $line->product_category_id,
                'product_category_name' => (string) $line->productCategory->name,
            ])
            ->values()
            ->all();
    }
}
