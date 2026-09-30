<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's favourite categories on one request module's tab strip (spec 0184).
 *
 * Not activity-logged and not backed by a Policy, same as UserTablePreference
 * (ADR-0004): per-user UI state with no audit value, self-scoped by
 * construction (the endpoints always key on the authenticated user) and gated
 * by the module's own viewAny.
 */
class UserCategoryTabPreference extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'module', 'favorite_category_ids', 'show_only_favorites'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'favorite_category_ids' => '[]',
        'show_only_favorites' => false,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'favorite_category_ids' => 'array',
            'show_only_favorites' => 'boolean',
        ];
    }
}
