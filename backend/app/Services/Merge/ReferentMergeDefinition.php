<?php

namespace App\Services\Merge;

use App\DataObjects\Users\ProfileData;
use App\Http\Resources\ReferentResource;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\User;
use App\Services\Merge\Collections\AddressesCollection;
use App\Services\Merge\Collections\ContactsCollection;
use App\Services\Merge\Collections\NotesCollection;
use App\Services\Merge\Collections\PivotCollection;
use App\Services\ReferentProfileWriter;
use App\Services\ReferentService;
use App\Services\Registries\PrimaryReferentElector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Merge of referents ("Referenti", spec 0394).
 */
final class ReferentMergeDefinition extends MergeDefinition
{
    public function __construct(
        private readonly ReferentProfileWriter $profileWriter,
        private readonly ReferentService $service,
        private readonly PrimaryReferentElector $primaryReferentElector,
    ) {}

    public function entity(): string
    {
        return 'referent';
    }

    public function table(): string
    {
        return 'referents';
    }

    public function modelClass(): string
    {
        return Referent::class;
    }

    protected function ownFields(): array
    {
        return [
            new MergeField('referent_type_id', MergeField::TYPE_RELATION, relationModel: ReferentType::class),
            new MergeField('contact_scope', MergeField::TYPE_ENUM),
            new MergeField('notes', MergeField::TYPE_TEXT, combinable: true),
            new MergeField('user_id', MergeField::TYPE_RELATION, relationModel: User::class),
            new MergeField('supplier_registry_id', MergeField::TYPE_RELATION, relationModel: Registry::class),
        ];
    }

    public function collections(): array
    {
        return [
            new ContactsCollection,
            new AddressesCollection,
            new PivotCollection('registries', 'referent_registry', 'referent_id', 'registry_id', Registry::class),
            new NotesCollection,
        ];
    }

    public function linkedSources(): array
    {
        return [
            new LinkedSource('opportunities', 'opportunities', ['opportunities.referent_id', 'opportunities.commercial_id', 'opportunities.reporter_id']),
            new LinkedSource('quotes', 'quotes', ['quotes.commercial_id', 'quotes.reporter_id']),
            new LinkedSource('registries_roles', 'registries', ['registries.commercial_id', 'registries.reporter_id', 'registries.primary_referent_id']),
            new LinkedSource('tasks', 'tasks', ['tasks.referent_id']),
            new LinkedSource('rewards', 'rewards', ['rewards.referent_id']),
            new LinkedSource('commission_settlements', 'commission_settlements', ['commission_settlements.referent_id']),
            new LinkedSource('agent_fiscal_profiles', 'agent_fiscal_profiles', ['agent_fiscal_profiles.referent_id']),
            new LinkedSource('projects', 'projects', ['projects.partner_id']),
            new LinkedSource('campaigns', 'campaigns', ['campaigns.partner_id']),
            ...PolymorphicLinks::for($this->entity()),
        ];
    }

    public function references(): MergeReferences
    {
        return new MergeReferences(
            plain: [
                ['opportunities', 'referent_id'],
                ['opportunities', 'commercial_id'],
                ['opportunities', 'reporter_id'],
                ['quotes', 'commercial_id'],
                ['quotes', 'reporter_id'],
                ['registries', 'commercial_id'],
                ['registries', 'reporter_id'],
                ['registries', 'primary_referent_id'],
                ['tasks', 'referent_id'],
                ['commission_settlements', 'referent_id'],
                ['agent_fiscal_profiles', 'referent_id'],
                ['projects', 'partner_id'],
                ['campaigns', 'partner_id'],
            ],
            conflicts: [['rewards', 'referent_id', ['reward_type_id', 'source_type', 'source_id']]],
            polymorphic: PolymorphicLinks::polymorphicReferences(),
        );
    }

    public function writeCard(Model $survivor, ProfileData $profile): void
    {
        $this->profileWriter->write($survivor, $profile);
    }

    /**
     * Registries whose principale was a merged referent now point at the
     * survivor: confirm it is still linked there, else re-elect (spec 0330).
     */
    public function settle(Model $survivor): void
    {
        Registry::query()
            ->where('primary_referent_id', $survivor->getKey())
            ->get()
            ->each(fn (Registry $registry) => $this->primaryReferentElector->elect($registry, $survivor->getKey()));
    }

    public function loadTree(Model $survivor): Model
    {
        /** @var Referent $survivor */
        return $this->service->loadProfileTree($survivor);
    }

    public function resource(Model $survivor, array $permissions): JsonResource
    {
        return new ReferentResource($survivor, $permissions['fields'] ?? []);
    }
}
