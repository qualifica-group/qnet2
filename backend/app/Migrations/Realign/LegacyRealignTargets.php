<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Migrations\Realign\Targets\InvoicesTarget;
use App\Migrations\Realign\Targets\OpportunitiesTarget;
use App\Migrations\Realign\Targets\ProformaRequestsTarget;
use App\Migrations\Realign\Targets\PurchaseInvoicesTarget;
use App\Migrations\Realign\Targets\PurchaseOrdersTarget;
use App\Migrations\Realign\Targets\PurchaseRequestsTarget;
use App\Migrations\Realign\Targets\QuotesTarget;
use App\Migrations\Realign\Targets\ReferentsTarget;
use App\Migrations\Realign\Targets\RegistriesTarget;
use App\Migrations\Realign\Targets\TasksTarget;
use App\Migrations\Realign\Targets\TimeEntriesTarget;
use App\Migrations\Realign\Targets\WorkOrdersTarget;
use App\Migrations\Realign\Targets\WorkOrderWorkflowStatusesTarget;

/**
 * The modules of the realignment, parents first: the status of a commessa needs the
 * commessa aligned, a task its links (spec 0375 data_contract).
 */
final class LegacyRealignTargets
{
    /** @var array<string, class-string<LegacyRealignTarget>> */
    public const array BY_SOURCE = [
        'registries' => RegistriesTarget::class,
        'referents' => ReferentsTarget::class,
        'opportunities' => OpportunitiesTarget::class,
        'quotes' => QuotesTarget::class,
        'work-orders' => WorkOrdersTarget::class,
        'work-order-workflow-statuses' => WorkOrderWorkflowStatusesTarget::class,
        'tasks' => TasksTarget::class,
        'time-entries' => TimeEntriesTarget::class,
        'purchase-requests' => PurchaseRequestsTarget::class,
        'purchase-orders' => PurchaseOrdersTarget::class,
        'purchase-invoices' => PurchaseInvoicesTarget::class,
        'proforma-requests' => ProformaRequestsTarget::class,
        'invoices' => InvoicesTarget::class,
    ];
}
