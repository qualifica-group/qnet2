<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\AssignmentTargetEnum;
use App\Models\Opportunity;
use App\Models\ProformaRequest;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds the "scheda dettagli" a notification email carries about the record
 * it is talking about (direttiva utente 2026-08-04).
 *
 * Returns an ORDERED map `i18n label key => value`, with keys from the
 * `notifications` lang file and NEVER a rendered label. Nothing is
 * translated here on purpose: `__()` must run at render time, inside the
 * per-recipient locale Laravel switches to via HasLocalePreference, or the
 * locale of whoever performed the write would be frozen onto every
 * recipient.
 *
 * A field with no value is OMITTED, never rendered as an empty row: a table
 * half full of dashes reads as broken data.
 *
 * Called from the SERVICE layer before dispatch, never from inside a
 * Notification: a Notification presents already-known facts and does not
 * query (the discipline RequestTransferredNotification documents).
 */
final class RecordDetails
{
    /**
     * Relations each builder reads, loaded in one batch by loadMissing() so a
     * batch write never degenerates into an N+1.
     *
     * @var array<int, string>
     */
    private const array REGISTRY_RELATIONS = ['personalData', 'source', 'supervisor', 'managers'];

    /**
     * @var array<int, string>
     */
    private const array OPPORTUNITY_RELATIONS = [
        'registry', 'supervisor', 'managers',
        'quotes.quoteWorkflowStatus',
        'operationalSite.addresses.city',
    ];

    /**
     * @var array<int, string>
     */
    private const array QUOTE_RELATIONS = [
        'opportunity.registry', 'source',
        'quoteWorkflowStatus', 'supervisor', 'operator',
        'operationalSite.addresses.city',
    ];

    /**
     * @var array<int, string>
     */
    private const array WORK_ORDER_RELATIONS = [
        'quote.opportunity.registry', 'workOrderWorkflowStatus', 'supervisors', 'participants',
    ];

    /**
     * @return array<string, string>
     */
    public static function for(Model $record): array
    {
        return match (true) {
            $record instanceof Registry => self::forRegistry($record),
            $record instanceof Opportunity => self::forOpportunity($record),
            $record instanceof Quote => self::forQuote($record),
            $record instanceof WorkOrder => self::forWorkOrder($record),
            $record instanceof Task => TaskDetails::for($record),
            $record instanceof Referent => ModuleRecordDetails::forReferent($record),
            $record instanceof ProformaRequest => ModuleRecordDetails::forProformaRequest($record),
            $record instanceof PurchaseRequest => ModuleRecordDetails::forPurchaseRequest($record),
            default => [],
        };
    }

    public static function targetFor(Model $record): AssignmentTargetEnum
    {
        return match (true) {
            $record instanceof Registry => AssignmentTargetEnum::Registry,
            $record instanceof Quote => AssignmentTargetEnum::Quote,
            $record instanceof WorkOrder => AssignmentTargetEnum::WorkOrder,
            default => AssignmentTargetEnum::Opportunity,
        };
    }

    /**
     * The record's name as the notification sentence quotes it: an Offerta
     * is known by its title (spec 0186), a Commessa by "code - title" (spec
     * 0351, as WorkOrderLinePaymentWriter already labels it), every other
     * record by its name.
     */
    public static function labelFor(Model $record): string
    {
        return match (true) {
            $record instanceof Quote => (string) $record->title,
            $record instanceof WorkOrder => "{$record->code} - {$record->title}",
            default => (string) $record->getAttribute('name'),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function forRegistry(Registry $registry): array
    {
        $registry->loadMissing(self::REGISTRY_RELATIONS);

        $personalData = $registry->personalData;

        return DetailFields::compact([
            'notifications.fields.name' => $registry->name,
            // The i18n KEY, not a rendered word: DetailsTable translates it in
            // the recipient's locale (see its value() method).
            'notifications.fields.type' => $registry->is_supplier ? 'notifications.values.supplier' : 'notifications.values.client',
            // Spec 0381 (U-1): the tax data, hidden by RecordMail from a
            // recipient who cannot open the record (D-4).
            'notifications.fields.vat_number' => $personalData?->vat_number,
            'notifications.fields.tax_code' => $personalData?->tax_code,
            'notifications.fields.sdi_code' => $personalData?->sdi_code,
            'notifications.fields.source' => $registry->source?->name,
            'notifications.fields.supervisor' => $registry->supervisor?->name,
            'notifications.fields.account_managers' => self::managerList($registry->managers),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function forOpportunity(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(self::OPPORTUNITY_RELATIONS);

        $site = $opportunity->operationalSite;

        return DetailFields::compact([
            'notifications.fields.title' => $opportunity->name,
            'notifications.fields.client' => $opportunity->registry?->name,
            'notifications.fields.operational_site' => $site === null ? null : OperationalSiteLabel::compose($site->primaryAddress),
            'notifications.fields.status' => self::statusLabel($opportunity),
            'notifications.fields.supervisor' => $opportunity->supervisor?->name,
            'notifications.fields.operator' => $opportunity->operatorManager()?->name,
        ]);
    }

    /**
     * Spec 0186: the Offerta's OWN facts — its title, Sede, working status,
     * Supervisore, Fonte (spec 0332) and GA2 Operatore (spec 0087, D-10: they can diverge from
     * the parent Opportunity's) — plus the client, which only the Opportunity
     * carries.
     *
     * @return array<string, string>
     */
    private static function forQuote(Quote $quote): array
    {
        $quote->loadMissing(self::QUOTE_RELATIONS);

        $site = $quote->operationalSite;

        return DetailFields::compact([
            'notifications.fields.title' => $quote->title,
            'notifications.fields.client' => $quote->opportunity?->registry?->name,
            'notifications.fields.operational_site' => $site === null ? null : OperationalSiteLabel::compose($site->primaryAddress),
            'notifications.fields.status' => $quote->quoteWorkflowStatus?->name,
            'notifications.fields.source' => $quote->source?->name,
            'notifications.fields.supervisor' => $quote->supervisor?->name,
            'notifications.fields.operator' => $quote->operator?->name,
        ]);
    }

    /**
     * Spec 0351: the Commessa's own facts — title, client of its offer,
     * "Stato lavorazione", start date and the whole team, so a new member
     * sees who else is on it.
     *
     * @return array<string, string>
     */
    private static function forWorkOrder(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing(self::WORK_ORDER_RELATIONS);

        return DetailFields::compact([
            'notifications.fields.title' => self::labelFor($workOrder),
            'notifications.fields.client' => $workOrder->quote?->opportunity?->registry?->name,
            'notifications.fields.status' => $workOrder->workOrderWorkflowStatus?->name,
            'notifications.fields.start_date' => $workOrder->start_date?->format('d/m/Y'),
            'notifications.fields.supervisors' => $workOrder->supervisors->pluck('name')->implode(', '),
            'notifications.fields.participants' => self::managerList($workOrder->participants),
        ]);
    }

    /**
     * The COMPUTED status (spec 0082) as plain text: the single status name,
     * or every distinct one comma-joined when the quotes disagree — a
     * notification body has no room for the badge's tooltip.
     */
    private static function statusLabel(Opportunity $opportunity): ?string
    {
        $names = array_column(app(OpportunityStatusResolver::class)->resolve($opportunity)['entries'], 'name');

        return $names === [] ? null : implode(', ', $names);
    }

    /**
     * The managers as "1. Ada Lovelace, 2. Alan Turing" — the pivot position
     * is the "G.A. n" slot and carries the order of importance, so it is part
     * of the information, not decoration.
     *
     * @param  Collection<int, User>  $managers
     */
    private static function managerList(iterable $managers): ?string
    {
        $parts = [];

        foreach ($managers as $manager) {
            $parts[] = $manager->pivot->position.'. '.$manager->name;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
