<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Enums\PurchaseRequestStatus;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\PurchaseDocumentTarget;
use App\Migrations\Realign\Support\PurchaseLegacyReferences;
use App\Migrations\Realign\Support\PurchaseRequestLineAligner;
use App\Migrations\Sources\PurchaseRequestsSource;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestPriority;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * RDA (spec 0375 D-11): header, closure and lines. Company and creator stay fixed.
 */
final class PurchaseRequestsTarget extends PurchaseDocumentTarget
{
    private const array HEADER_COLUMNS = [
        'subject', 'requested_at', 'customer_id', 'work_order_id', 'operational_site_id', 'business_function_id', 'notes',
        'delivery_terms', 'procurement_plan', 'technical_requirements', 'special_conditions', 'status', 'closed_at', 'close_reason',
        'taxable_total', 'vat_total', 'grand_total',
    ];

    private const array LINE_COLUMNS = [
        'position', 'product_id', 'description', 'supplier_id', 'reason', 'unit_of_measure_id', 'quantity', 'unit_price', 'vat_rate_id',
        'taxable_amount', 'vat_amount', 'total_amount', 'status', 'approved_at',
    ];

    public function __construct(
        PurchaseLegacyReferences $references,
        private readonly PurchaseRequestsSource $requestSource,
        private readonly PurchaseRequestLineAligner $lineAligner,
    ) {
        parent::__construct($references);
    }

    public function source(): string
    {
        return 'purchase-requests';
    }

    protected function modelClass(): string
    {
        return PurchaseRequest::class;
    }

    public function label(Model $record): string
    {
        return (string) $record->getAttribute('subject');
    }

    /**
     * The lines count: their status changes are logged on them, not on the request.
     */
    public function touchSubjects(Model $record): array
    {
        return [
            [PurchaseRequest::class, $record->getKey()],
            [PurchaseRequestLine::class, PurchaseRequestLine::query()->where('purchase_request_id', $record->getKey())->select('id')],
        ];
    }

    public function snapshot(Model $record): array
    {
        /** @var PurchaseRequest $record */
        return [
            ...$this->columns($record, self::HEADER_COLUMNS),
            'priority' => PurchaseRequestPriority::query()->whereKey($record->purchase_request_priority_id)->value('name'),
            'requester' => $this->userName($record->requester_id),
            'function_manager' => $this->userName($record->function_manager_id),
            'closed_by' => $this->userName($record->closed_by),
            ...$this->rowFields($record->lines()->orderBy('id')->get(), 'line', 'old_id', self::LINE_COLUMNS),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var PurchaseRequest $record */
        $warnings = [];

        // Step 1: the header fields.
        $record->forceFill($this->header($record, $legacy, $warnings));

        // Step 2: the lines, then the closure, which depends on their status.
        $linesChanged = $this->lineAligner->align($record, (array) ($legacy['lines'] ?? []), $warnings, $notes);
        $record->forceFill($this->closure($record, $legacy, $warnings));

        // Step 3: one quiet save.
        $this->finish($record, $linesChanged, $legacy, $warnings, $notes);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function header(PurchaseRequest $record, array $legacy, array &$warnings): array
    {
        return [
            'subject' => $this->references->capped($legacy['subject'] ?? null, PurchaseRequestsSource::SUBJECT_MAX, 'Subject', $warnings) ?? $record->subject,
            'requested_at' => $this->references->text($legacy['requested_at'] ?? null) ?? $record->requested_at,
            'purchase_request_priority_id' => $this->requestSource->priorityId($legacy['priority_name'] ?? null, $warnings),
            'requester_id' => $this->references->required(User::class, $legacy['requester_user_id'] ?? null, 'Requester'),
            'function_manager_id' => $this->references->required(User::class, $legacy['function_manager_user_id'] ?? null, 'Function manager'),
            'operational_site_id' => $this->references->required(OperationalSite::class, $legacy['operational_site_id'] ?? null, 'Operational site'),
            'business_function_id' => $this->references->required(BusinessFunction::class, $legacy['business_function_id'] ?? null, 'Business function'),
            'work_order_id' => ($legacy['work_order_id'] ?? null) === null ? null : $this->references->required(WorkOrder::class, $legacy['work_order_id'], 'Work order'),
            'customer_id' => $this->references->optionalOrCurrent(Registry::class, $legacy['customer_id'] ?? null, $record->customer_id, 'Customer', $warnings),
            'notes' => $this->references->text($legacy['notes'] ?? null),
            'delivery_terms' => $this->references->text($legacy['delivery_terms'] ?? null),
            'procurement_plan' => $this->references->text($legacy['procurement_plan'] ?? null),
            'technical_requirements' => $this->references->text($legacy['technical_requirements'] ?? null),
            'special_conditions' => $this->references->text($legacy['special_conditions'] ?? null),
        ];
    }

    /**
     * The import's closure rule. An already closed request keeps its date when the
     * legacy has none: that date would otherwise be the moving `updated_at`.
     *
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function closure(PurchaseRequest $record, array $legacy, array &$warnings): array
    {
        $closure = $this->requestSource->closure($record, $legacy, $warnings);

        if ($closure === []) {
            return ['status' => PurchaseRequestStatus::Open, 'closed_by' => null, 'closed_at' => null, 'close_reason' => null];
        }

        if (! $record->isClosed()) {
            return $closure;
        }

        if (! (bool) ($legacy['is_closed'] ?? false)) {
            return [];
        }

        return blank($legacy['closed_at'] ?? null) ? array_diff_key($closure, ['closed_at' => true]) : $closure;
    }
}
