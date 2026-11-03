<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\ImportMappingTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved column-mapping snapshot for one import domain (spec 0035),
 * team-shared (readable by anyone who passes the domain's own import gates,
 * unlike TableFilterView's private/shared toggle) but delete-restricted to
 * its creator (ImportMappingTemplatePolicy). `resource` mirrors
 * `import_runs.resource` (the `{domain}` key) — NOT a foreign key, same
 * convention as ImportRun. `columns` is the ORDERED column key snapshot
 * (App\Imports\Support\ColumnAnalysis::columnKeys()) matched EXACTLY against
 * a newly uploaded file's detected columns (ImportRunPayloadBuilder) to
 * surface `matching_template`.
 */
class ImportMappingTemplate extends BaseModel
{
    /** @use HasFactory<ImportMappingTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'resource',
        'user_id',
        'name',
        'columns',
        'column_mapping',
        'value_mappings',
        'dedup_strategy',
    ];

    protected $casts = [
        'resource' => 'string',
        'name' => 'string',
        'columns' => 'array',
        'column_mapping' => 'array',
        'value_mappings' => 'array',
        'dedup_strategy' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
