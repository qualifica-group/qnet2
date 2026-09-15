import type { NoteQuoteScope } from '@/features/notes/types'

/**
 * Centralized TanStack Query keys for the collaborative notes feature (spec
 * 0052). Every key is parametric on the host entity (`entityType`/`entityId`,
 * D-9/D-14) — the feature never bakes in a specific module.
 */
export const notesKeys = {
  /**
   * Prefisso di TUTTE le liste di un record, qualunque sia lo scope. Le
   * mutation invalidano questa: fissarle su uno scope (`list(...)` senza
   * argomento) mancherebbe la lista filtrata o quella bloccata su un'Offerta,
   * e la nota appena scritta comparirebbe solo ricaricando la pagina.
   */
  lists: (entityType: string, entityId: number) => ['notes', entityType, entityId] as const,
  /**
   * Keyset-paginated root list of a single host record. Spec 0085: la chiave
   * include lo SCOPE — due filtri diversi sono due liste diverse, e senza
   * questo TanStack Query servirebbe la cache di uno all'altro.
   */
  list: (entityType: string, entityId: number, quoteScope: NoteQuoteScope = 'all') =>
    [...notesKeys.lists(entityType, entityId), { quoteScope }] as const,
}
