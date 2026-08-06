import type { NoteQuoteScope } from '@/features/notes/types'

/**
 * Centralized TanStack Query keys for the collaborative notes feature (spec
 * 0052). Every key is parametric on the host entity (`entityType`/`entityId`,
 * D-9/D-14) — the feature never bakes in a specific module.
 */
export const notesKeys = {
  /**
   * Keyset-paginated root list of a single host record. Spec 0085: la chiave
   * include lo SCOPE — due filtri diversi sono due liste diverse, e senza
   * questo TanStack Query servirebbe la cache di uno all'altro.
   */
  list: (entityType: string, entityId: number, quoteScope: NoteQuoteScope = 'all') =>
    ['notes', entityType, entityId, { quoteScope }] as const,
  /**
   * Contextual mention lookup of a single host record (D-10), keyed by the
   * active search term so each search starts a fresh paginated query.
   */
  mentionable: (entityType: string, entityId: number, search: string) =>
    ['notes', entityType, entityId, 'mentionable-users', { search }] as const,
}
