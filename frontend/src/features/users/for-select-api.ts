import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the users for-select endpoint. */
export const USERS_FOR_SELECT_RESOURCE = 'users'

/**
 * The operator's own Sede (spec 0048), as composed by
 * `UserForSelectResource::meta` from the user's `EmploymentProfile`. Lets the
 * Lead form auto-fill `operational_site_id` when the Operatore is chosen
 * first — omitted/null when the user has no employment Sede.
 */
export interface UserForSelectMeta {
  operational_site_id: number | null
  operational_site_label: string | null
}

/**
 * A single user option as returned by `GET /api/users/for-select`, carrying
 * its Sede presentation bag so a caller can auto-fill a dependent Sede field
 * without a second fetch (mirrors `OperationalSiteForSelectItem`).
 */
export interface UserForSelectItem extends ForSelectItem {
  meta?: UserForSelectMeta
}

/**
 * Fetches a page of user options from `GET /api/users/for-select`. Thin wrapper
 * over the generic for-select fetcher, bound to the `users` resource. For users
 * the item carries `label` (name) and `subtitle` (email). `params.operational_site_id`
 * (spec 0048) filters to the operators of that Sede, `params.opportunity_id` (directive
 * 2026-08-06) to that opportunity's Gestori Account — the only users an Offerta accepts as
 * Supervisore. Omitted, every user is returned.
 */
export function fetchUsersForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(USERS_FOR_SELECT_RESOURCE, params)
}

interface UseUsersForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
  /** Extra query params, e.g. `{ operational_site_id }` (spec 0048) to scope the Sede's operators. */
  params?: Record<string, string | number>
}

/**
 * Reusable hook feeding the users multi-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `users` resource.
 */
export function useUsersForSelect({
  search,
  ids,
  enabled,
  params,
}: UseUsersForSelectOptions) {
  return useForSelect({
    resource: USERS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
    params,
  })
}
