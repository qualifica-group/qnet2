<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\ProformaRequest;
use App\Models\PurchaseRequest;
use App\Models\Referent;

/**
 * The detail cards of the records a notification email reaches only through
 * a note or a request (spec 0381, D-6): Referente, Richiesta proforma,
 * Richiesta di acquisto. Kept apart from RecordDetails, which serves the
 * assignment targets and is near the file-size threshold.
 *
 * Same contract as RecordDetails: an ordered `i18n label key => value` map,
 * empty fields omitted, terms that must be translated passed as their
 * `notifications.values.*` key (DetailsTable translates them in the
 * recipient's locale).
 */
final class ModuleRecordDetails
{
    /**
     * @var array<int, string>
     */
    private const array REFERENT_RELATIONS = ['referentType', 'registries'];

    /**
     * @var array<int, string>
     */
    private const array PROFORMA_REQUEST_RELATIONS = ['workOrder', 'supplier', 'assignee'];

    private const string DATE_FORMAT = 'd/m/Y';

    /**
     * @return array<string, string>
     */
    public static function forReferent(Referent $referent): array
    {
        $referent->loadMissing(self::REFERENT_RELATIONS);

        return DetailFields::compact([
            'notifications.fields.name' => $referent->name,
            'notifications.fields.referent_type' => $referent->referentType?->name,
            'notifications.fields.registries' => $referent->registries->pluck('name')->implode(', '),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function forProformaRequest(ProformaRequest $proformaRequest): array
    {
        $proformaRequest->loadMissing(self::PROFORMA_REQUEST_RELATIONS);

        $workOrder = $proformaRequest->workOrder;

        return DetailFields::compact([
            'notifications.fields.work_order' => $workOrder === null ? null : RecordDetails::labelFor($workOrder),
            'notifications.fields.type' => self::proformaValue($proformaRequest->kind?->value),
            'notifications.fields.supplier' => $proformaRequest->supplier?->name,
            'notifications.fields.status' => self::proformaValue($proformaRequest->status?->value),
            'notifications.fields.assignee' => $proformaRequest->assignee?->name,
        ]);
    }

    /**
     * Moved here from PurchaseRequestNotifier, same facts: subject,
     * priority, request date, total.
     *
     * @return array<string, string>
     */
    public static function forPurchaseRequest(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->loadMissing('purchaseRequestPriority:id,name');

        return DetailFields::compact([
            'notifications.fields.subject' => $purchaseRequest->subject,
            'notifications.fields.priority' => $purchaseRequest->purchaseRequestPriority?->name,
            'notifications.fields.requested_on' => $purchaseRequest->requested_at?->format(self::DATE_FORMAT),
            'notifications.fields.total' => $purchaseRequest->grand_total === null ? null : (string) $purchaseRequest->grand_total,
        ]);
    }

    /**
     * The `notifications.values.proforma_*` key of a kind or status, so the
     * card uses the same words as the Proforma screen ("Ente", "Da evadere").
     */
    private static function proformaValue(?string $value): ?string
    {
        return $value === null ? null : 'notifications.values.proforma_'.$value;
    }
}
