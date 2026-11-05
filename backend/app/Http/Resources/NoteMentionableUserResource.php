<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * For-select projection of a mentionable User (GET /api/notes/mentionable-users,
 * spec 0052 data_contract): same {id, label, subtitle, avatar_url} shape as
 * every other for-select item (ADR 0011), kept as its OWN resource — not
 * UserForSelectResource — so this endpoint never leaks that resource's
 * `meta` (operational site) block, which is unrelated to mentionability.
 *
 * @mixin User
 */
class NoteMentionableUserResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->email,
            'avatar_url' => $this->avatarDataUri(),
            'in_record' => (bool) $this->in_record,
            'has_module_access' => (bool) $this->has_module_access,
        ];
    }
}
