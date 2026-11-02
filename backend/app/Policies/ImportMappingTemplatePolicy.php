<?php

namespace App\Policies;

use App\Models\ImportMappingTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Owner-only authorization for a saved ImportMappingTemplate (spec 0035).
 *
 * List/create stay gated by the SAME double gate as the CSV template
 * download (ImportMappingTemplateController) — this Policy exists ONLY for
 * delete, because a team-shared template is a real cross-user access surface
 * once another operator relies on it, mirroring TableFilterViewPolicy.
 *
 * Deliberately NOT extending BasePolicy (Spatie permission-backed): ownership,
 * not a permission string, is the rule here. The global super-admin bypass
 * (Gate::before in AppServiceProvider) already grants a super-admin actor
 * every ability, so it is NOT duplicated here.
 */
class ImportMappingTemplatePolicy
{
    public function delete(User $user, ImportMappingTemplate $template): Response
    {
        return $template->user_id === $user->id
            ? Response::allow()
            : Response::deny(__('authorization.import_mapping_template_not_owner'));
    }
}
