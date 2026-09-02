<?php

namespace App\Tables;

use App\CustomFields\CustomFieldEntityRegistry;
use App\Tables\Quotes\OpportunityScopedTableDefinition;
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
     * The only domain wrapped in `RequestManagementScopedTableDefinition`
     * (spec 0064; spec 0084 dropped its `attr.*`-column effect): the
     * category tab strip's row scope + GA2 relabel are a
     * `request-management`-specific concept, not a generic table-framework
     * one (unlike custom fields).
     */
    private const string REQUEST_MANAGEMENT_DOMAIN = 'request-management';

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

    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the definition for the given domain, wrapped in
     * `CustomFieldAwareTableDefinition` (spec 0021) when the domain is
     * custom-fieldable, THEN in `RequestManagementScopedTableDefinition`
     * (spec 0064/0084) for `request-management`, THEN in
     * `OpportunityScopedTableDefinition` (spec 0067) for `quotes`, THEN in
     * `QuoteScopedTableDefinition` (spec 0095) for `work-orders` — one line
     * each here, zero per-module code.
     *
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolve(string $domain): TableDefinition
    {
        $definition = $this->wrapIfCustomFieldable($domain, $this->resolveRaw($domain));
        $definition = $this->wrapIfRequestManagementScoped($domain, $definition);
        $definition = $this->wrapIfOpportunityScoped($domain, $definition);

        return $this->wrapIfQuoteScoped($domain, $definition);
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
     * `request-management` only — every other domain is returned unchanged.
     */
    private function wrapIfRequestManagementScoped(string $domain, TableDefinition $definition): TableDefinition
    {
        if ($domain !== self::REQUEST_MANAGEMENT_DOMAIN) {
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
