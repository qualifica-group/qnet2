<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The mentionable modules of the AI assistant (spec 0347, D-4): 18 kebab-case
 * keys, the same as the `for-select` URL prefix and the policy permission prefix.
 */
final class AiMentionRegistry
{
    /** @var array<string, AiMentionDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<int, string>
     */
    public function modules(): array
    {
        return array_keys($this->definitions());
    }

    public function has(string $module): bool
    {
        return isset($this->definitions()[$module]);
    }

    public function definition(string $module): ?AiMentionDefinition
    {
        return $this->definitions()[$module] ?? null;
    }

    /**
     * The modules the user may view (`{module}.view`), in registry order.
     *
     * @return array<int, string>
     */
    public function availableModules(User $user): array
    {
        return array_values(array_filter(
            $this->modules(),
            fn (string $module): bool => $this->canView($user, $this->definitions()[$module]),
        ));
    }

    private function canView(User $user, AiMentionDefinition $definition): bool
    {
        try {
            return $user->can($definition->viewPermission());
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * @return array<string, AiMentionDefinition>
     */
    private function definitions(): array
    {
        return $this->definitions ??= collect($this->build())->keyBy('module')->all();
    }

    /**
     * @return array<int, AiMentionDefinition>
     */
    private function build(): array
    {
        return [
            new AiMentionDefinition('companies', Company::class, static fn (Model $m): string => (string) $m->denomination,
                ['denomination', 'vat_number']),
            new AiMentionDefinition('company-sites', CompanySite::class, static fn (Model $m): string => (string) $m->name,
                ['name', 'notes', 'is_default'], ['company' => 'denomination']),
            new AiMentionDefinition('referents', Referent::class, static fn (Model $m): string => (string) $m->name,
                ['name', 'contact_scope', 'notes'], ['referentType' => 'name', 'supplierRegistry' => 'name']),
            new AiMentionDefinition('registries', Registry::class, static fn (Model $m): string => (string) $m->name,
                ['name', 'vat_group', 'is_supplier', 'is_qualified_supplier', 'agreement_status', 'agreement_notes', 'general_notes', 'size_class', 'employee_count'],
                ['source' => 'name', 'supervisor' => 'name', 'commercial' => 'name', 'reporter' => 'name', 'primaryReferent' => 'name']),
            new AiMentionDefinition('leads', Lead::class, static fn (Model $m): string => $m->registry?->name ?? '#'.$m->id,
                ['notes', 'extra_fields'],
                ['registry' => 'name', 'campaign' => 'name', 'operationalSite' => 'alias', 'source' => 'name', 'operator' => 'name']),
            new AiMentionDefinition('opportunities', Opportunity::class, static fn (Model $m): string => (string) $m->name,
                ['name', 'start_date', 'estimated_value', 'expected_close_date', 'success_probability', 'general_notes', 'is_transferred'],
                ['registry' => 'name', 'referent' => 'name', 'commercial' => 'name', 'reporter' => 'name', 'supervisor' => 'name', 'operationalSite' => 'alias']),
            new AiMentionDefinition('campaigns', Campaign::class, static fn (Model $m): string => (string) $m->name,
                ['code', 'name', 'description', 'start_date', 'end_date', 'total_budget', 'target_lead'],
                ['project' => 'name', 'partner' => 'name', 'pipelineStatus' => 'name', 'operationalSite' => 'alias', 'source' => 'name']),
            new AiMentionDefinition('quotes', Quote::class, static fn (Model $m): string => self::codeAndTitle($m->code, $m->title),
                ['code', 'title', 'quote_date', 'internal_notes', 'next_callback_at', 'revenue_net', 'revenue_vat', 'cost_net', 'cost_vat', 'margin_net'],
                ['opportunity' => 'name', 'quoteWorkflowStatus' => 'name', 'commercial' => 'name', 'reporter' => 'name', 'supervisor' => 'name',
                    'operator' => 'name', 'company' => 'denomination', 'companySite' => 'name', 'operationalSite' => 'alias', 'source' => 'name', 'paymentMethod' => 'name']),
            new AiMentionDefinition('work-orders', WorkOrder::class, static fn (Model $m): string => self::codeAndTitle($m->code, $m->title),
                ['code', 'title', 'type', 'start_date', 'callback_date', 'description', 'internal_notes', 'is_force_closed', 'force_close_reason'],
                ['quote' => ['code', 'title'], 'workOrderWorkflowStatus' => 'name', 'supervisors' => 'name']),
            new AiMentionDefinition('projects', Project::class, static fn (Model $m): string => (string) $m->name,
                ['code', 'name', 'description', 'start_date', 'end_date', 'total_budget', 'target_lead'],
                ['pipelineStatus' => 'name', 'partner' => 'name', 'operationalSite' => 'alias']),
            new AiMentionDefinition('tasks', Task::class, static fn (Model $m): string => (string) $m->title,
                ['title', 'description', 'start_date', 'end_date', 'completion_date', 'estimated_minutes', 'is_blocked', 'closure_feedback'],
                ['taskStatus' => 'name', 'taskType' => 'name', 'taskCategory' => 'name', 'taskPriority' => 'name', 'taskImportance' => 'name',
                    'registry' => 'name', 'opportunity' => 'name', 'workOrder' => ['code', 'title'], 'requester' => 'name', 'creator' => 'name',
                    'parentTask' => 'title', 'assignees' => 'name']),
            new AiMentionDefinition('products', Product::class, static fn (Model $m): string => (string) $m->name,
                ['code', 'name', 'description', 'cost', 'price', 'product_type', 'usages'],
                ['category' => 'name', 'vatRate' => 'name', 'unitOfMeasure' => 'name', 'productTypology' => 'name']),
            new AiMentionDefinition('invoices', Invoice::class, static fn (Model $m): string => $m->number.'/'.$m->year,
                ['number', 'year', 'type', 'document_date', 'external_number', 'external_date', 'net_amount', 'vat_amount', 'total_amount', 'notes', 'internal_note', 'deviation'],
                ['company' => 'denomination', 'customer' => 'name', 'paymentMethod' => 'name', 'workOrder' => 'code', 'quote' => 'code', 'invoiceTag' => 'name']),
            new AiMentionDefinition('purchase-invoices', PurchaseInvoice::class, static fn (Model $m): string => $m->document_number ?? '#'.$m->id,
                ['document_type', 'document_number', 'document_date', 'net_amount', 'vat_amount', 'withholding_amount', 'enasarco_amount', 'total_amount', 'notes'],
                ['company' => 'denomination', 'supplier' => 'name', 'paymentMethod' => 'name', 'costType' => 'name', 'workOrder' => 'code']),
            new AiMentionDefinition('purchase-orders', PurchaseOrder::class, static fn (Model $m): string => $m->code(),
                ['number', 'year', 'order_date', 'description', 'general_notes', 'status', 'taxable_total', 'vat_total', 'grand_total', 'sector_notes'],
                ['supplier' => 'name', 'company' => 'denomination', 'companySite' => 'name', 'paymentMethod' => 'name', 'customer' => 'name',
                    'workOrder' => 'code', 'quote' => 'code', 'opportunity' => 'name']),
            new AiMentionDefinition('purchase-requests', PurchaseRequest::class, static fn (Model $m): string => (string) $m->subject,
                ['subject', 'requested_at', 'status', 'notes', 'delivery_terms', 'procurement_plan', 'technical_requirements', 'special_conditions',
                    'taxable_total', 'vat_total', 'grand_total', 'close_reason'],
                ['purchaseRequestPriority' => 'name', 'requester' => 'name', 'functionManager' => 'name', 'customer' => 'name', 'workOrder' => 'code',
                    'company' => 'denomination', 'companySite' => 'name', 'businessFunction' => 'name']),
            new AiMentionDefinition('contracts', Contract::class, static fn (Model $m): string => $m->quote === null ? '#'.$m->id : self::codeAndTitle($m->quote->code, $m->quote->title),
                ['accepted_at', 'validated_at', 'renewal_date', 'expiry_date', 'terminated_at', 'termination_reason', 'payment_notes', 'comments', 'suspended_at'],
                ['contractStatus' => 'name', 'quote' => ['code', 'title']]),
            new AiMentionDefinition('users', User::class, static fn (Model $m): string => (string) $m->name,
                ['name', 'email', 'locale', 'is_active'], ['businessFunctions' => 'name']),
        ];
    }

    /** Same "code — title" composition as the quote for-select label. */
    private static function codeAndTitle(?string $code, ?string $title): string
    {
        $title = trim((string) $title);

        return $title === '' ? (string) $code : $code.' — '.$title;
    }
}
