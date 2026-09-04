<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Support\OperationalSiteLabel;
use App\Tables\Shared\OfferLinesColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Row projection for the `request-management` domain (spec 0086: the row is
 * now a `quotes` record, D-1 — migrated off the former `opportunities`-rooted
 * grid, spec 0049): turns an eager-loaded Quote into the operative row
 * payload the grid renders.
 *
 * Split out of RequestManagementTableDefinition so the definition keeps a
 * single concern (query building: scoping, filters, sorts, distinct values)
 * and the per-row presentation lives here. Every value is resolved from
 * relations already loaded by the definition's baseQuery — this mapper never
 * queries, EXCEPT `quote_workflow_status_options` (user directive
 * 2026-08-31, restoring spec 0054 D-9's precedent on the record this module
 * now IS): the valid-destination set is resolved PER OFFER (spec 0083's
 * workflow criteria), so `GET /columns`' domain-wide `options` alone cannot
 * tell the frontend which of them apply to THIS row. `$workflowResolver` is
 * injected once per request and MEMOIZES its own domain-wide queries
 * (activeWorkflows()/statusesFor()), so a page of N rows costs at most one
 * query for the candidate workflows plus one per DISTINCT resolved workflow
 * on the page — never N.
 *
 * D-9: `opportunity_id` rides along as the record the `documents`/`notes`/
 * `activity` row actions and the field-change-request "current value" for
 * `source` key off — documents/notes/activity stay anchored to the
 * Opportunity (two sibling offers show the same thread), while `source` etc.
 * are re-derived THROUGH `quote.opportunity` for display. `pending_change_requests`
 * does NOT follow that split (D-10, corrected in execution): it counts the
 * QUOTE's own field-change-requests — two sibling offers carry independent
 * badges.
 */
final class RequestRowMapper
{
    public function __construct(private readonly QuoteWorkflowResolver $workflowResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function map(Quote $row): array
    {
        $opportunity = $row->opportunity;

        return [
            'id' => $row->id,
            // D-9: the record documents/notes/activity/history keep reading —
            // both stay anchored to the Opportunity, never the offer.
            'opportunity_id' => $row->opportunity_id,
            // "Fonte" (user directive 2026-07-31): re-derived through the
            // offer's opportunity (spec 0086) — the `{id, name}` ref both the
            // relation cell and its inline picker read.
            'source' => $this->summarize($opportunity?->source),
            // "Richieste di modifica in attesa" (spec 0078, AC-037; spec
            // 0086, D-10 corrected): the OFFER's own pending requests —
            // `withCount('pendingFieldChangeRequests')` on the Quote itself
            // already excludes approved/rejected requests, so two sibling
            // offers carry independent badges.
            'pending_change_requests' => (int) ($row->pending_field_change_requests_count ?? 0),
            // "Note generali" (user directive 2026-07-31; spec 0086, D-11):
            // still `opportunity.general_notes`, display-only in this module.
            'general_notes' => $opportunity?->general_notes,
            // "Categoria prodotto": the OPPORTUNITY's own product lines (spec
            // 0075), re-derived through `quote.opportunity` (spec 0086).
            // Projected as the {funzione aziendale, categoria} PAIRS — ids
            // for the inline editor to commit, names for the cell to render.
            'product_categories' => $this->productLinePairs($opportunity),
            // The `product_categories` inline editor's OWN scope (spec 0075,
            // D-6): the opportunity's product-line category ids, unchanged in
            // shape from the former ProductsOfInterestColumn::SCOPE_COLUMN
            // projection (data contract: "invariata come scope dell'editor
            // delle linee").
            'product_category_ids' => $this->productCategoryIds($opportunity),
            // "Linee di prodotto" (spec 0086, D-7): the offer's own REVENUE
            // lines' products, replacing "Prodotti di interesse" on this
            // domain only (AC-021).
            ...OfferLinesColumn::project($row),
            // "Operatore": spec 0087, D-9 — the offer's own GA2 Operatore, a
            // real FK on `quotes` (`operator_id`), denormalized from the
            // `quote_user` pivot.
            'operator_ga2' => $this->userSummary($row->operator),
            // Spec 0056/0086 D-6: the Sede operativa is now the OFFER's own
            // FK — the site has no own name, so its label is composed
            // server-side from its primary address.
            'operational_site' => OperationalSiteLabel::summarize($row->operationalSite),
            // "Trasferito" (spec 0079; spec 0086, D-6 — migrated to `quotes`,
            // per-offer rather than per-deal): a real column, never derived —
            // display-only, no inline editor exists for it (AC-024/AC-035).
            'is_transferred' => (bool) $row->is_transferred,
            ...$this->clientAnagraphics($opportunity),
            // "Prossimo richiamo" (spec 0052 D-1/D-5): the OFFER's own real
            // column since the user directive 2026-09-04, same wire format as
            // RequestManagementResource so FE date parsing stays identical.
            'next_callback_at' => $row->next_callback_at?->format('Y-m-d\TH:i'),
            // Hidden column, drives the default "most recently loaded first"
            // sort only — the OFFER's own `created_at` now (AC-014).
            'created_at' => $row->created_at,
            // "Stato di lavorazione" (user directive 2026-08-31): the OFFER's
            // own working state. `color` rides along so the cell paints the
            // very dot the work panel's picker and the Offerte grid already
            // show for that status.
            'quote_workflow_status' => $this->summarizeWorkflowStatus($row->quoteWorkflowStatus),
            // The ids THIS offer may actually be moved to (the workflow spec
            // 0083 resolves for it), so the select editor narrows the
            // domain-wide `options` of GET /columns down to what is valid
            // here — the 422 QuoteWorkflowStatusWriter raises stays the
            // security net, this is the UX layer on top of it.
            'quote_workflow_status_options' => $this->allowedWorkflowStatusIds($row),
        ];
    }

    /**
     * The destination ids the select editor offers for this row: the ordered
     * set of the workflow QuoteWorkflowResolver resolves for THIS offer.
     *
     * @return array<int, int>
     */
    private function allowedWorkflowStatusIds(Quote $row): array
    {
        return $this->workflowResolver
            ->statusesFor($this->workflowResolver->resolve($row))
            ->pluck('id')
            ->all();
    }

    /**
     * The working-state summary the badge cell renders and the inline select
     * pre-selects: the same `{id, name, color}` projection
     * QuotesTableDefinition emits for this very relation.
     *
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeWorkflowStatus(?QuoteWorkflowStatus $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * The client anagraphic columns, read from the Registry's PersonalData
     * card (phone = its primary phone/mobile contact), through the offer's
     * opportunity (spec 0086: `quotes` carries no `registry_id` of its own).
     *
     * @return array<string, string|null>
     */
    private function clientAnagraphics(?Opportunity $opportunity): array
    {
        $card = $opportunity?->registry?->personalData;

        return [
            'first_name' => $card?->first_name,
            'last_name' => $card?->last_name,
            'tax_code' => $card?->tax_code,
            'phone' => $this->primaryPhone($card?->contacts),
        ];
    }

    /**
     * A person summary carrying the inline avatar (data URI) for the shared
     * UserCell — mirrors QuotesTableDefinition::userSummary/
     * OpportunitiesTableDefinition::userSummary. Null when unset.
     *
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatarDataUri()];
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
    private function productLinePairs(?Opportunity $opportunity): array
    {
        return ($opportunity?->productLines ?? collect())
            ->filter(static fn (OpportunityProductLine $line): bool => $line->businessFunction !== null && $line->productCategory !== null)
            ->map(static fn (OpportunityProductLine $line): array => [
                'business_function_id' => (int) $line->business_function_id,
                'business_function_name' => (string) $line->businessFunction->name,
                'product_category_id' => (int) $line->product_category_id,
                'product_category_name' => (string) $line->productCategory->name,
            ])
            ->values()
            ->all();
    }

    /**
     * The `product_categories` inline editor's scope (spec 0075, D-6): the
     * distinct product-line category ids of the offer's opportunity.
     *
     * @return array<int, int>
     */
    private function productCategoryIds(?Opportunity $opportunity): array
    {
        return ($opportunity?->productLines ?? collect())
            ->pluck('product_category_id')
            ->unique()
            ->values()
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
