<?php

namespace App\Services\Merge;

use Illuminate\Database\Eloquent\Model;

/**
 * One mergeable scalar field (spec 0394, D-4): `onCard` fields live on the
 * personal-data card, the others on the record itself. `relationModel` is set
 * for `relation` fields so the preview can show a readable `display`.
 */
final readonly class MergeField
{
    public const string TYPE_TEXT = 'text';

    public const string TYPE_NUMBER = 'number';

    public const string TYPE_BOOLEAN = 'boolean';

    public const string TYPE_DATE = 'date';

    public const string TYPE_ENUM = 'enum';

    public const string TYPE_RELATION = 'relation';

    /**
     * @param  class-string<Model>|null  $relationModel
     */
    public function __construct(
        public string $key,
        public string $type,
        public bool $onCard = false,
        public bool $combinable = false,
        public ?string $relationModel = null,
    ) {}
}
