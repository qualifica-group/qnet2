<?php

namespace App\Tables;

use App\CustomFields\CustomFieldEntityRegistry;
use App\Tables\RequestManagement\AttributeScopedTableDefinition;
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
     * The only domain wrapped in `AttributeScopedTableDefinition` (spec
     * 0064): category-attribute columns are a `request-management`-specific
     * concept, not a generic table-framework one (unlike custom fields).
     */
    private const string REQUEST_MANAGEMENT_DOMAIN = 'request-management';

    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the definition for the given domain, wrapped in
     * `CustomFieldAwareTableDefinition` (spec 0021) when the domain is
     * custom-fieldable, THEN in `AttributeScopedTableDefinition` (spec 0064)
     * for `request-management` — one line each here, zero per-module code.
     * Column order this composition yields: native, then `custom.*`, then
     * `attr.*`.
     *
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolve(string $domain): TableDefinition
    {
        $definition = $this->wrapIfCustomFieldable($domain, $this->resolveRaw($domain));

        return $this->wrapIfAttributeScoped($domain, $definition);
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
     * Wrap in `AttributeScopedTableDefinition` (spec 0064) for
     * `request-management` only — every other domain is returned unchanged.
     */
    private function wrapIfAttributeScoped(string $domain, TableDefinition $definition): TableDefinition
    {
        if ($domain !== self::REQUEST_MANAGEMENT_DOMAIN) {
            return $definition;
        }

        /** @var AttributeScopedTableDefinition $wrapped */
        $wrapped = $this->container->make(AttributeScopedTableDefinition::class, [
            'inner' => $definition,
        ]);

        return $wrapped;
    }
}
