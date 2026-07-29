import type { QueryClient } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import type { OpportunityManagerRef } from '@/features/opportunities/types'

/** The registry `meta` block feeding the Opportunity form's prefill (spec 0040 BR-4 + A-5). */
export interface RegistryMeta {
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  /** Directive 2026-07-29: inherited alongside commercial/reporter when an anagrafica is picked. */
  supervisor: RelationFieldRef | null
  /** Account managers (registry_user), ordered by position — A-5 prefill of `manager_slots`. */
  managers: OpportunityManagerRef[]
}

/**
 * `registries/for-select` item extended with the `meta.commercial`/`meta.reporter`
 * defaults (spec 0040 BR-4) and `meta.managers` (A-5). Not modeled on the
 * generic `ForSelectItem` (mirrors the `StatusForSelectItem` pattern in
 * `features/status-reorder/api.ts`): the shared type stays domain-agnostic.
 */
interface RegistryForSelectItem extends ForSelectItem {
  meta?: RegistryMeta
}

/**
 * Imperative one-shot fetch of the newly selected registry's `meta` block
 * (prefill of commercial/reporter/supervisor), run as a direct consequence of the
 * user's `onChange` — never as a render-time effect, so a later background
 * refetch can never silently overwrite the user's own edits.
 */
export async function fetchOpportunityRegistryMeta(
  queryClient: QueryClient,
  registryId: number,
): Promise<RegistryMeta | null> {
  const page = await queryClient.fetchQuery({
    queryKey: ['registries', 'meta-for-opportunity', registryId],
    queryFn: () => fetchForSelect(REGISTRIES_FOR_SELECT_RESOURCE, { ids: [registryId] }),
  })
  const item = page.items[0] as RegistryForSelectItem | undefined
  return item?.meta ?? null
}
