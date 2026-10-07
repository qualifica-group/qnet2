<?php

namespace App\Tables;

use App\CustomFields\CustomFieldEntityRegistry;
use App\RequestManagement\RequestModule;
use App\Tables\Quotes\OpportunityScopedTableDefinition;
use App\Tables\Registries\RegistryScopedTableDefinition;
use App\Tables\RequestManagement\RequestManagementScopedTableDefinition;
use App\Tables\WorkOrders\QuoteScopedTableDefinition;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{domain}` string → its TableDefinition.
 *
 * Registration is an explicit config map (config/tables.php) resolved through
 * the container, so a definition's dependencies (e.g. UserService) are injected.
 * Adding a domain = write one XxxTableDefinition + add one line to that config.
 * No new Controller/Service/Request/Resource/route.
 *
 * Unknown domain → ModelNotFoundException → 404 (via BaseApiController). The
 * {domain} segment is user-controlled: only config-mapped domains resolve;
 * everything else is unreachable.
 */
class TableRegistry
{
    /**
     * The only domain wrapped in `OpportunityScopedTableDefinition` (spec
     * 0067): scoping the Offerte grid to one Opportunity is a `quotes`-
     * specific concept, not a generic table-framework one.
     */
    private const string QUOTES_DOMAIN = 'quotes';

    /**
     * The only domain wrapped in `QuoteScopedTableDefinition` (spec 0095):
     * scoping the Commesse grid to one Quote (the Contratto detail's
     * "Commesse" tab) is a `work-orders`-specific concept, not a generic
     * table-framework one.
     */
    private const string WORK_ORDERS_DOMAIN = 'work-orders';

    /**
     * Domains wrapped in `RegistryScopedTableDefinition` (spec 0199), each
     * with the fully-qualified column holding the client. `quotes` and
     * `work-orders` are not listed: their own scoped decorators implement
     * `RegistryScopable` (the client is reachable only through joins).
     */
    private const array REGISTRY_SCOPE_COLUMNS = [
        'opportunities' => 'opportunities.registry_id',
        'tasks' => 'tasks.registry_id',
        'commission-configurations' => 'commission_configurations.recipient_id',
    ];

    /**
     * The morph type column of the REGISTRY_SCOPE_COLUMNS entries that are
     * polymorphic (spec 0204): the Configuratore commissioni's recipient.
     */
    private const array REGISTRY_SCOPE_MORPH_TYPE_COLUMNS = [
        'commission-configurations' => 'commission_configurations.recipient_type',
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the definition for the given domain, wrapped in
     * `CustomFieldAwareTableDefinition` (spec 0021) when the domain is
     * custom-fieldable, THEN in `RequestManagementScopedTableDefinition`
     * (spec 0064/0084) for `request-management`/`enrollee-management` (spec
     * 0130), THEN in `OpportunityScopedTableDefinition` (spec 0067) for
     * `quotes`, THEN in `QuoteScopedTableDefinition` (spec 0095) for
     * `work-orders`, THEN in `RegistryScopedTableDefinition` (spec 0199) for
     * `opportunities`/`tasks`/`commission-configurations` (spec 0204) — one
     * line each here, zero per-module code.
     *
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolve(string $domain): TableDefinition
    {
        $definition = $this->wrapIfCustomFieldable($domain, $this->resolveRaw($domain));
        $definition = $this->wrapIfRequestManagementScoped($domain, $definition);
        $definition = $this->wrapIfOpportunityScoped($domain, $definition);
        $definition = $this->wrapIfQuoteScoped($domain, $definition);

        return $this->wrapIfRegistryScoped($domain, $definition);
    }

    /**
     * The undecorated definition for `$domain`, straight from
     * config/tables.php. Used internally by `resolve()` AND by
     * `CustomFieldEntityRegistry::build()` (spec 0021), which only needs the
     * raw `modelClass()`/`resource()` identity (byte-identical whether the
     * definition is wrapped or not — the decorator passes both through
     * unchanged) — going through the decorated `resolve()` there would
     * re-enter `isCustomFieldable()` before it finishes building its own map,
     * an infinite recursion.
     *
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolveRaw(string $domain): TableDefinition
    {
        /** @var array<string, class-string<TableDefinition>> $definitions */
        $definitions = config('tables.definitions', []);

        $class = $definitions[$domain] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(TableDefinition::class, [$domain]);
        }

        /** @var TableDefinition $definition */
        $definition = $this->container->make($class);

        return $definition;
    }

    /**
     * Wrap in `CustomFieldAwareTableDefinition` (spec 0021) when `$domain` is
     * custom-fieldable. `custom-fields` itself (the admin CRUD for
     * definitions) is never wrapped: a custom field cannot itself carry
     * custom fields.
     */
    private function wrapIfCustomFieldable(string $domain, TableDefinition $definition): TableDefinition
    {
        if ($domain === 'custom-fields') {
            return $definition;
        }

        /** @var CustomFieldEntityRegistry $entityRegistry */
        $entityRegistry = $this->container->make(CustomFieldEntityRegistry::class);

        if (! $entityRegistry->isCustomFieldable($domain)) {
            return $definition;
        }

        /** @var CustomFieldAwareTableDefinition $wrapped */
        $wrapped = $this->container->make(CustomFieldAwareTableDefinition::class, [
            'inner' => $definition,
            'entityType' => $domain,
        ]);

        return $wrapped;
    }

    /**
     * Wrap in `RequestManagementScopedTableDefinition` (spec 0064/0084) for
     * `request-management` AND `enrollee-management` (spec 0130: the category
     * tab strip's row scope + GA relabel are a `RequestModule`-family concept,
     * not a generic table-framework one, unlike custom fields) — every other
     * domain is returned unchanged. `RequestModule::tryFrom()` is the single
     * membership test (constraints: no `if ($module === ...)` scattered
     * outside `RequestModule` itself).
     */
    private function wrapIfRequestManagementScoped(string $domain, TableDefinition $definition): TableDefinition
    {
        if (RequestModule::tryFrom($domain) === null) {
            return $definition;
        }

        /** @var RequestManagementScopedTableDefinition $wrapped */
        $wrapped = $this->container->make(RequestManagementScopedTableDefinition::class, [
            'inner' => $definition,
        ]);

        return $wrapped;
    }

    /**
     * Wrap in `OpportunityScopedTableDefinition` (spec 0067) for `quotes`
     * only — every other domain is returned unchanged.
     */
    private function wrapIfOpportunityScoped(string $domain, TableDefinition $definition): TableDefinition
    {
        if ($domain !== self::QUOTES_DOMAIN) {
            return $definition;
        }

        /** @var OpportunityScopedTableDefinition $wrapped */
        $wrapped = $this->container->make(OpportunityScopedTableDefinition::class, [
            'inner' => $definition,
        ]);

        return $wrapped;
    }

    /**
     * Wrap in `RegistryScopedTableDefinition` (spec 0199) for the domains
     * listed in `REGISTRY_SCOPE_COLUMNS` only — every other domain is
     * returned unchanged.
     */
    private function wrapIfRegistryScoped(string $domain, TableDefinition $definition): TableDefinition
    {
        $column = self::REGISTRY_SCOPE_COLUMNS[$domain] ?? null;

        if ($column === null) {
            return $definition;
        }

        /** @var RegistryScopedTableDefinition $wrapped */
        $wrapped = $this->container->make(RegistryScopedTableDefinition::class, [
            'inner' => $definition,
            'registryColumn' => $column,
            'morphTypeColumn' => self::REGISTRY_SCOPE_MORPH_TYPE_COLUMNS[$domain] ?? null,
        ]);

        return $wrapped;
    }

    /**
     * Wrap in `QuoteScopedTableDefinition` (spec 0095) for `work-orders`
     * only — every other domain is returned unchanged.
     */
    private function wrapIfQuoteScoped(string $domain, TableDefinition $definition): TableDefinition
    {
        if ($domain !== self::WORK_ORDERS_DOMAIN) {
            return $definition;
        }

        /** @var QuoteScopedTableDefinition $wrapped */
        $wrapped = $this->container->make(QuoteScopedTableDefinition::class, [
            'inner' => $definition,
        ]);

        return $wrapped;
    }
}
