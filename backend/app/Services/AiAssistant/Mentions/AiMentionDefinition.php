<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * How one mentionable module is read: the model, the label, and the explicit
 * allow-list of columns and related columns sent to the provider. Nothing outside
 * the allow-list ever leaves the server, whatever the model exposes.
 *
 * `$relations` maps a relation name to the column(s) to project: a string gives a
 * scalar (or a list for a to-many relation), an array gives an object per related
 * row. The relations are eager-loaded, so the projection never lazy-loads.
 *
 * `$gatedFields` maps a policy ability to the fields of `$fields` projected
 * only when the user holds it on the record (spec 0412: the quote's costs).
 */
final readonly class AiMentionDefinition
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  Closure(Model): string  $label
     * @param  array<int, string>  $fields
     * @param  array<string, string|array<int, string>>  $relations
     * @param  array<string, array<int, string>>  $gatedFields
     */
    public function __construct(
        public string $module,
        public string $modelClass,
        public Closure $label,
        public array $fields,
        public array $relations = [],
        public array $gatedFields = [],
    ) {}

    /** The module key is the policy resource prefix, so the permission is "{module}.view". */
    public function viewPermission(): string
    {
        return $this->module.'.view';
    }
}
