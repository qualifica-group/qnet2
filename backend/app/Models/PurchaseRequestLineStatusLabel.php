<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseRequestLineStatusLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Presentation of one purchase request line status (spec 0248, D-1): name,
 * badge color/icon and display order of a value of PurchaseRequestLineStatus.
 * The rows are the enum cases, created by the migration: the enum stays the
 * only source of the logic, nothing server-side decides on name/color/icon.
 *
 * `system_key` (= the enum value) is deliberately NOT fillable: it is written
 * only by the migration and never changes.
 */
#[Fillable(['name', 'color', 'icon', 'sort_order'])]
class PurchaseRequestLineStatusLabel extends BaseModel
{
    /** @use HasFactory<PurchaseRequestLineStatusLabelFactory> */
    use HasFactory, LogsModelActivity;

    protected $table = 'purchase_request_line_statuses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'int'];
    }
}
