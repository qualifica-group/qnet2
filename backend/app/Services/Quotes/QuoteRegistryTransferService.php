<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Services\Opportunities\OpportunityNameWriter;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\Opportunities\OpportunityQuoteLimit;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use App\Services\OpportunityService;
use App\Support\ManagerPositions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec 0404, part B: moves an offer to another anagrafica. The offer hangs off
 * an Opportunity (the anagrafica is `opportunities.registry_id`), so moving it
 * means re-parenting it onto an opportunity of the destination:
 *
 *  - the destination's open opportunity, when the one-open-opportunity rule
 *    would refuse a second one for the same categories (B-D3);
 *  - otherwise a NEW opportunity, created through OpportunityService with the
 *    old one's product lines, commercial, reporter, supervisor, operational
 *    site and team; no referent (it belongs to the old anagrafica).
 *
 * The old opportunity is left intact (R-2): its own notes and documents stay,
 * only the notes scoped to THIS offer (`notes.quote_id`) travel with it. One
 * explicit `registry_transferred` activity entry is written on the offer and
 * on both opportunities (B-D5); the automatic model log is suspended so the
 * move is not recorded twice.
 */
final class QuoteRegistryTransferService
{
    /** Activity event name, rendered by ActivityLogEntryResource like an org-chart move. */
    public const string EVENT = 'registry_transferred';

    /** The ability required on top of `update`, in Offerte and in both request modules (B-D1). */
    public const string ABILITY = 'transferRegistry';

    public function __construct(
        private readonly RegistryOpenOpportunityGuard $openOpportunityGuard,
        private readonly OpportunityQuoteLimit $quoteLimit,
        private readonly OpportunityService $opportunityService,
        private readonly OpportunityProductLineCoverage $coverage,
        private readonly QuoteManagerWriter $managerWriter,
        private readonly QuoteAttributeValueWriter $attributeValueWriter,
        private readonly OpportunityNameWriter $nameWriter,
    ) {}

    /**
     * @param  array<string, mixed>|null  $attributeValues  the "Informazioni aggiuntive" edited in the dialog, validated like the offer update
     *
     * @throws ValidationException same anagrafica, offer under contract, or the destination opportunity accepts no further offer
     */
    public function transfer(Quote $quote, int $registryId, ?array $attributeValues, User $actor): Quote
    {
        return DB::transaction(function () use ($quote, $registryId, $attributeValues, $actor): Quote {
            $quote->loadMissing(['opportunity.registry', 'opportunity.productLines', 'opportunity.managers', 'managers']);
            $origin = $quote->opportunity;
            $destination = Registry::query()->findOrFail($registryId);

            // Step 1: refuse what cannot move (B-D6) before writing anything.
            $this->assertTransferable($quote, $origin, $destination);

            // Step 2: the opportunity the offer lands on (B-D3).
            $target = $this->targetOpportunity($origin, $destination, $actor);

            // Step 3: re-parent the offer, its scoped notes, coverage and team.
            $this->moveQuote($quote, $origin, $target);

            // Step 4: the dialog's "Informazioni aggiuntive", against the new set.
            $this->applyAttributeValues($quote, $attributeValues);

            // Step 5: both cards re-derive their automatic title.
            $this->nameWriter->recalculate($origin->id);
            $this->nameWriter->recalculate($target->id);

            // Step 6: the explicit audit trail (B-D5).
            $this->log($quote, $origin, $target, $destination, $actor);

            return $quote->refresh();
        });
    }

    private function assertTransferable(Quote $quote, Opportunity $origin, Registry $destination): void
    {
        if ($origin->registry_id === $destination->id) {
            throw ValidationException::withMessages(['registry_id' => [__('quotes.registry_transfer.same_registry')]]);
        }

        if ($quote->contract()->exists()) {
            throw ValidationException::withMessages(['registry_id' => [__('quotes.registry_transfer.contracted')]]);
        }
    }

    private function targetOpportunity(Opportunity $origin, Registry $destination, User $actor): Opportunity
    {
        $categoryIds = $origin->productLines->pluck('product_category_id')->map(intval(...))->all();
        $open = $this->openOpportunityGuard->blockingOpportunityFor($destination->id, $categoryIds);

        if ($open === null) {
            return $this->opportunityService->create($this->newOpportunityData($origin, $destination), $actor);
        }

        if (! $this->quoteLimit->allowsAdditionalQuote($open)) {
            throw ValidationException::withMessages(['registry_id' => [__(OpportunityQuoteLimit::SINGLE_QUOTE_MESSAGE)]]);
        }

        return $open;
    }

    private function newOpportunityData(Opportunity $origin, Registry $destination): CreateOpportunityData
    {
        return new CreateOpportunityData(
            registryId: $destination->id,
            referentId: null,
            commercialId: $origin->commercial_id,
            reporterId: $origin->reporter_id,
            supervisorId: $origin->supervisor_id,
            leadId: null,
            managerSlots: ManagerPositions::slotsFor($origin, $origin->managers->modelKeys()),
            productLines: $origin->productLines
                ->map(static fn ($line): array => ['product_category_id' => (int) $line->product_category_id])
                ->all(),
            startDate: null,
            estimatedValue: null,
            expectedCloseDate: null,
            successProbability: null,
            operationalSiteId: $origin->operational_site_id,
        );
    }

    private function moveQuote(Quote $quote, Opportunity $origin, Opportunity $target): void
    {
        $quote->disableLogging();
        $quote->forceFill(['opportunity_id' => $target->id])->save();
        $quote->setRelation('opportunity', $target);
        $quote->enableLogging();

        Note::query()
            ->where('notable_id', $origin->id)
            ->where('notable_type', $origin->getMorphClass())
            ->where('quote_id', $quote->id)
            ->update(['notable_id' => $target->id]);

        // An existing destination may not cover the offer's products yet.
        $products = Product::query()
            ->with('category')
            ->whereIn('id', $quote->offerLines()->pluck('product_id'))
            ->get();
        $this->coverage->ensure($target, $products, 'registry_id');

        // The team must belong to the new opportunity (spec 0087 D-6).
        $this->managerWriter->sync($quote, ManagerPositions::slotsFor($quote, $quote->managers->modelKeys()), true);
    }

    /**
     * @param  array<string, mixed>|null  $attributeValues
     */
    private function applyAttributeValues(Quote $quote, ?array $attributeValues): void
    {
        if ($attributeValues === null) {
            return;
        }

        $changed = [];
        $old = [];
        $this->attributeValueWriter->apply($quote, $attributeValues, $changed, $old);

        if ($changed !== []) {
            $quote->save();
        }
    }

    private function log(Quote $quote, Opportunity $origin, Opportunity $target, Registry $destination, User $actor): void
    {
        $properties = [
            'kind' => 'registry_id',
            'from' => ['id' => $origin->registry_id, 'name' => $origin->registry?->name],
            'to' => ['id' => $destination->id, 'name' => $destination->name],
            'from_opportunity_id' => $origin->id,
            'to_opportunity_id' => $target->id,
        ];

        foreach ([$quote, $origin, $target] as $subject) {
            activity($subject->getTable())
                ->performedOn($subject)
                ->causedBy($actor)
                ->event(self::EVENT)
                ->withProperties($properties)
                ->log('Quote transferred to another registry');
        }
    }
}
