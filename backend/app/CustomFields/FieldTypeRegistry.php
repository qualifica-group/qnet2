<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\CustomFields\Exceptions\UnknownFieldTypeException;
use App\CustomFields\Types\FieldTypeHandler;
use Illuminate\Contracts\Container\Container;

/**
 * Maps a custom field `type` string → its FieldTypeHandler (spec 0021 —
 * FIELD TYPE STRATEGY, AC-003). Mirrors App\Tables\TableRegistry /
 * App\Authorization\AuthorizationRegistry: an explicit config map
 * (config/custom-fields.php) resolved through the container, so a handler's
 * own dependencies (e.g. RelationFieldType's CustomFieldEntityRegistry) are
 * injected. Adding a type = one handler class + one config line (OCP).
 *
 * Unknown type → UnknownFieldTypeException (never a silent fallback: a
 * misconfigured/unsupported `type` on a definition must fail loudly at the
 * point it is resolved, not degrade into an unclassified field).
 */
class FieldTypeRegistry
{
    /**
     * Enum key every `type` badge column declares (TableDefinition::enumKeyFor),
     * so the frontend localizes the type catalogue from a single i18n namespace
     * (`enums.custom_field_type.<value>`) instead of the raw backend label.
     */
    public const ENUM_KEY = 'custom_field_type';

    public function __construct(private readonly Container $container) {}

    /**
     * @throws UnknownFieldTypeException when the type is not registered.
     */
    public function resolve(string $type): FieldTypeHandler
    {
        $class = $this->definitions()[$type] ?? null;

        if ($class === null) {
            throw UnknownFieldTypeException::forType($type);
        }

        /** @var FieldTypeHandler */
        return $this->container->make($class);
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->definitions());
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * @return array<string, class-string<FieldTypeHandler>>
     */
    private function definitions(): array
    {
        return config('custom-fields.types', []);
    }
}
