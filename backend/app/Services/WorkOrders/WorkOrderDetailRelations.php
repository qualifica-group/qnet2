<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

/**
 * The relations eager-loaded for the commessa detail (WorkOrderResource), so
 * one read never N+1s. Moved out of WorkOrderService (file size).
 *
 * The client tree is loaded from both places a client can live (spec 0431,
 * D-6): the offer's opportunity, and the commessa's own `registry` when it
 * was born without an offer. An unset FK costs nothing, so a commessa of
 * either kind loads only the branch it has.
 */
final class WorkOrderDetailRelations
{
    /** Where WorkOrderReferences finds the client: the offer's, or the commessa's own. */
    private const array REGISTRY_PATHS = ['quote.opportunity.registry', 'registry'];

    /**
     * Below the client: its documents (spec 0173), primary contacts and
     * Referente (task 428287), and the "Info cliente" block (spec 0400).
     */
    private const array REGISTRY_TREE = [
        'personalData.contacts',
        'primaryReferent.referentType',
        'primaryReferent.personalData.contacts',
        'personalData.addresses.city',
        'personalData.addresses.province',
        'personalData.addresses.state',
        'personalData.addresses.country',
        'secondaryReferent.referentType',
        'secondaryReferent.personalData.contacts',
        'commercial.referentType',
        'commercialPartner.referentType',
        'sectors',
        'customFieldValueRow',
    ];

    private const array OTHER_RELATIONS = [
        // `.contract`: the Contratto WorkOrderResource names instead of the offer.
        'quote.contract',
        // Spec 0098, AC-016: the attribute trio resolves off the lines' categories.
        'quoteLines.product.category',
        // The typology badge (Consulenza/Ente) on each line's "Stato pagamento".
        'quoteLines.product.productTypology',
        // Spec 0267: the client each line comes from, shown on the line of a multi-offer commessa.
        'quoteLines.quote.opportunity.registry',
        'supervisors',
        'participants',
        // Spec 0322: the "Stato lavorazione" WorkOrderResource exposes.
        'workOrderWorkflowStatus',
        // Spec 0124: the `task_template` summary.
        'taskTemplate',
        // The Societa' e sedi block, off the offer or the commessa's own (spec 0431).
        'quote.company',
        'quote.companySite',
        'quote.operationalSite.addresses.city',
        'company',
        'companySite',
        'operationalSite.addresses.city',
        // Spec 0331: the offer data WorkOrderResource projects read-only.
        'quote.opportunity.referent.referentType',
        'quote.opportunity.referent.personalData.contacts',
        // Spec 0403: the payment status of each line in THIS commessa.
        'linePayments.status',
        'quote.source',
        // The business functions, off the offer's opportunity or the commessa's own lines (spec 0431).
        'quote.opportunity.productLines.productCategory',
        'quote.opportunity.productLines.businessFunction',
        'productLines.productCategory',
        'productLines.businessFunction',
        // Spec 0431 (D-17): the products of a commessa born without an offer.
        'products',
        'quote.commercial',
        'quote.commercialPartner',
        'quote.reporter',
        'quote.layout',
        'quote.paymentMethod',
        'quote.rewards.rewardType',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $registryRelations = [];
        foreach (self::REGISTRY_PATHS as $path) {
            $registryRelations[] = $path;
            foreach (self::REGISTRY_TREE as $relation) {
                $registryRelations[] = $path.'.'.$relation;
            }
        }

        return [...self::OTHER_RELATIONS, ...$registryRelations];
    }
}
