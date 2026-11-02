import type { QueryClient } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import type { OpportunityManagerRef } from '@/features/opportunities/types'

/** The registry `meta` block feeding the Opportunity/Task forms' prefill (spec 0040 BR-4 + A-5, spec 0330). */
export interface RegistryForSelectMeta {
  is_qualified_supplier: boolean
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  /** Directive 2026-07-29: inherited alongside commercial/reporter when an anagrafica is picked. */
  supervisor: RelationFieldRef | null
  /** Account managers (registry_user), ordered by position — A-5 prefill of `manager_slots`. */
  managers: OpportunityManagerRef[]
  /** Spec 0330: the registry's main referent, prefilled into the Referente of opportunities and tasks. */
  primary_referent: RelationFieldRef | null
}

/**
 * `registries/for-select` item extended with the `meta` block. Not modeled on
 * the generic `ForSelectItem` (mirrors the `StatusForSelectItem` pattern in
 * `features/status-reorder/api.ts`): the shared type stays domain-agnostic.
 */
interface RegistryForSelectItem extends ForSelectItem {
  meta?: RegistryForSelectMeta
}

/**
 * Imperative one-shot fetch of the newly selected registry's `meta` block,
 * run as a direct consequence of the user's `onChange` — never as a
 * render-time effect, so a later background refetch can never silently
 * overwrite the user's own edits.
 */
export async function fetchRegistryForSelectMeta(
  queryClient: QueryClient,
  registryId: number,
): Promise<RegistryForSelectMeta | null> {
  const page = await queryClient.fetchQuery({
    queryKey: ['registries', 'meta-for-opportunity', registryId],
    queryFn: () => fetchForSelect(REGISTRIES_FOR_SELECT_RESOURCE, { ids: [registryId] }),
  })
  // `ids[]` does not filter: the endpoint returns the first page by name and
  // appends the requested id, so `items[0]` is a different anagrafica.
  const item = page.items.find((option) => option.id === registryId) as RegistryForSelectItem | undefined
  return item?.meta ?? null
}
