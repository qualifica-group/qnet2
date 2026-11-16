<?php

namespace App\Services\Merge;

use App\DataObjects\Users\ProfileData;
use App\Http\Resources\RegistryResource;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Source;
use App\Models\User;
use App\Services\Merge\Collections\AddressesCollection;
use App\Services\Merge\Collections\ContactsCollection;
use App\Services\Merge\Collections\DocumentsCollection;
use App\Services\Merge\Collections\ManagersCollection;
use App\Services\Merge\Collections\NotesCollection;
use App\Services\Merge\Collections\PivotCollection;
use App\Services\Registries\PrimaryReferentElector;
use App\Services\RegistryProfileWriter;
use App\Services\RegistryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Merge of registries ("Anagrafiche", spec 0394).
 */
final class RegistryMergeDefinition extends MergeDefinition
{
    public function __construct(
        private readonly RegistryProfileWriter $profileWriter,
        private readonly RegistryService $service,
        private readonly PrimaryReferentElector $primaryReferentElector,
    ) {}

    public function entity(): string
    {
        return 'registry';
    }

    public function table(): string
    {
        return 'registries';
    }

    public function modelClass(): string
    {
        return Registry::class;
    }

    protected function ownFields(): array
    {
        return [
            new MergeField('source_id', MergeField::TYPE_RELATION, relationModel: Source::class),
            new MergeField('vat_group', MergeField::TYPE_TEXT),
            new MergeField('is_supplier', MergeField::TYPE_BOOLEAN),
            new MergeField('is_qualified_supplier', MergeField::TYPE_BOOLEAN),
            new MergeField('agreement_status', MergeField::TYPE_ENUM),
            new MergeField('agreement_notes', MergeField::TYPE_TEXT, combinable: true),
            new MergeField('general_notes', MergeField::TYPE_TEXT, combinable: true),
            new MergeField('size_class', MergeField::TYPE_ENUM),
            new MergeField('employee_count', MergeField::TYPE_NUMBER),
            new MergeField('supervisor_id', MergeField::TYPE_RELATION, relationModel: User::class),
            new MergeField('commercial_id', MergeField::TYPE_RELATION, relationModel: Referent::class),
            new MergeField('reporter_id', MergeField::TYPE_RELATION, relationModel: Referent::class),
            new MergeField('primary_referent_id', MergeField::TYPE_RELATION, relationModel: Referent::class),
        ];
    }

    public function collections(): array
    {
        return [
            new ContactsCollection,
            new AddressesCollection,
            new PivotCollection('sectors', 'sector_registry', 'registry_id', 'sector_id', Sector::class),
            new PivotCollection('referents', 'referent_registry', 'registry_id', 'referent_id', Referent::class),
            new ManagersCollection,
            new NotesCollection,
            new DocumentsCollection,
        ];
    }

    public function linkedSources(): array
    {
        $viaOpportunity = [['opportunities', 'quotes.opportunity_id', 'opportunities.id']];

        return [
            new LinkedSource('opportunities', 'opportunities', ['opportunities.registry_id']),
            new LinkedSource('quotes', 'quotes', ['opportunities.registry_id'], $viaOpportunity),
            new LinkedSource('work_orders', 'work_orders', ['opportunities.registry_id'], [['quotes', 'work_orders.quote_id', 'quotes.id'], ['opportunities', 'quotes.opportunity_id', 'opportunities.id']]),
            new LinkedSource('leads', 'leads', ['leads.registry_id']),
            new LinkedSource('tasks', 'tasks', ['tasks.registry_id']),
            new LinkedSource('time_entries', 'time_entries', ['time_entries.registry_id']),
            new LinkedSource('invoices', 'invoices', ['invoices.customer_registry_id']),
            new LinkedSource('purchase_invoices', 'purchase_invoices', ['purchase_invoices.supplier_id']),
            new LinkedSource('purchase_orders', 'purchase_orders', ['purchase_orders.supplier_id', 'purchase_orders.customer_id']),
            new LinkedSource('purchase_requests', 'purchase_requests', ['purchase_requests.customer_id']),
            new LinkedSource('purchase_requests', 'purchase_request_lines', ['purchase_request_lines.supplier_id']),
            new LinkedSource('proforma_requests', 'proforma_requests', ['proforma_requests.supplier_id']),
            new LinkedSource('supplied_products', 'product_suppliers', ['product_suppliers.supplier_id']),
            new LinkedSource('treasury_movements', 'treasury_movements', ['treasury_movements.registry_id']),
            new LinkedSource('supplied_quote_lines', 'quote_lines', ['quote_lines.supplier_id']),
            new LinkedSource('work_order_costs', 'work_order_costs', ['work_order_costs.supplier_id']),
            new LinkedSource('installment_compensations', 'installment_compensations', ['installment_compensations.registry_id']),
            new LinkedSource('paid_agents', 'referents', ['referents.supplier_registry_id']),
            ...PolymorphicLinks::for($this->entity()),
        ];
    }

    public function references(): MergeReferences
    {
        return new MergeReferences(
            plain: [
                ['opportunities', 'registry_id'],
                ['leads', 'registry_id'],
                ['tasks', 'registry_id'],
                ['time_entries', 'registry_id'],
                ['invoices', 'customer_registry_id'],
                ['purchase_invoices', 'supplier_id'],
                ['purchase_orders', 'supplier_id'],
                ['purchase_orders', 'customer_id'],
                ['purchase_requests', 'customer_id'],
                ['purchase_request_lines', 'supplier_id'],
                ['proforma_requests', 'supplier_id'],
                ['quote_lines', 'supplier_id'],
                ['work_order_costs', 'supplier_id'],
                ['treasury_movements', 'registry_id'],
                ['installment_compensations', 'registry_id'],
                ['referents', 'supplier_registry_id'],
            ],
            conflicts: [['product_suppliers', 'supplier_id', ['product_id']]],
            polymorphic: PolymorphicLinks::polymorphicReferences(),
        );
    }

    public function writeCard(Model $survivor, ProfileData $profile): void
    {
        $this->profileWriter->write($survivor, $profile);
    }

    public function settle(Model $survivor): void
    {
        /** @var Registry $survivor */
        $this->primaryReferentElector->elect($survivor, $survivor->primary_referent_id);

        if (! $survivor->is_supplier && $survivor->is_qualified_supplier) {
            $survivor->update(['is_qualified_supplier' => false]);
        }
    }

    public function loadTree(Model $survivor): Model
    {
        /** @var Registry $survivor */
        return $this->service->loadProfileTree($survivor);
    }

    public function resource(Model $survivor, array $permissions): JsonResource
    {
        return new RegistryResource($survivor);
    }
}
