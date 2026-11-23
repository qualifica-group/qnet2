<?php

declare(strict_types=1);

namespace Integrations\Formalab\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One class (course edition) of the Formalab catalogue, mirrored locally by
 * the periodic sync. `payload_hash` is the fingerprint of the mapped columns:
 * work orders are rewritten only when it differs from their link's.
 */
#[Fillable([
    'edition_id', 'code', 'title', 'label', 'status', 'course_status',
    'start_date', 'end_date', 'total_hours', 'exam_date', 'ip_collection_date',
    'advance_decree', 'advance_credited_at', 'balance_decree', 'balance_credited_at',
    'site_name', 'site_city', 'site_code', 'learners_count', 'payload_hash', 'last_seen_at',
])]
class FormalabClass extends Model
{
    protected $table = 'formalab_classes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edition_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'total_hours' => 'integer',
            'exam_date' => 'date',
            'ip_collection_date' => 'date',
            'advance_credited_at' => 'date',
            'balance_credited_at' => 'date',
            'learners_count' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
