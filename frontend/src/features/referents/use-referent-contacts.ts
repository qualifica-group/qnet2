import { useQuery } from '@tanstack/react-query'
import {
  fetchReferentsForSelect,
  REFERENTS_FOR_SELECT_RESOURCE,
} from '@/features/referents/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'

/** Contact channel kinds (mirrors backend `ContactTypeEnum`). */
export type ReferentContactType = 'phone' | 'fax' | 'email' | 'pec' | 'website'

/** A referent's primary contact, as projected in `referents/for-select` `meta.contacts` (spec 0040 A-4). */
export interface ReferentContact {
  type: ReferentContactType
  label: string | null
  value: string
  is_primary: boolean
}

/** `referents/for-select` item carrying the `meta.contacts` marker (mirrors the registry-meta pattern). */
interface ReferentForSelectItem extends ForSelectItem {
  meta?: { contacts: ReferentContact[] }
}

/**
 * The primary contacts of a single referent, read from the `referents/for-select`
 * `meta.contacts` block via an `ids:[id]` hydration query (spec 0040 A-4).
 * Server state, so it lives in TanStack Query (never a render effect); disabled
 * until a referent is actually selected. Empty array when the referent has no
 * primary contacts. Reused by the referent/commercial/reporter recap.
 */
export function useReferentContacts(referentId: number | null) {
  return useQuery({
    queryKey: [REFERENTS_FOR_SELECT_RESOURCE, 'contacts', referentId],
    enabled: referentId !== null,
    queryFn: async (): Promise<ReferentContact[]> => {
      const page = await fetchReferentsForSelect({ ids: [referentId as number] })
      const item = page.items.find((option) => option.id === referentId) as
        | ReferentForSelectItem
        | undefined
      return item?.meta?.contacts ?? []
    },
  })
}
