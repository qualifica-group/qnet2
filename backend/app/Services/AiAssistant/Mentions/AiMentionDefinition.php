<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * How one mentionable module is read: the model and the label shown to the
 * person. The label never reaches the provider (spec 0433: the model gets only
 * module and id). `$with` lists the relations the label reads, so resolving
 * never lazy-loads.
 */
final readonly class AiMentionDefinition
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  Closure(Model): string  $label
     * @param  array<int, string>  $with
     */
    public function __construct(
        public string $module,
        public string $modelClass,
        public Closure $label,
        public array $with = [],
    ) {}

    /** The module key is the policy resource prefix, so the permission is "{module}.view". */
    public function viewPermission(): string
    {
        return $this->module.'.view';
    }
}
