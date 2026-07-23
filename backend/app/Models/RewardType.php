<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\RewardTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Reward type lookup entity (spec 0058): a pure anagraphic (name, color)
 * describing the TYPES of voucher/reward/incentive usable in the CRM.
 * `name` is unique (BR-1); `color` is a mandatory palette token (D-5), never
 * an allow-listed enum (D-6). No relation: no entity references
 * `reward_types` in this version (BR-3).
 */
#[Fillable(['name', 'color'])]
class RewardType extends BaseModel
{
    /** @use HasFactory<RewardTypeFactory> */
    use HasFactory, LogsModelActivity;
}
