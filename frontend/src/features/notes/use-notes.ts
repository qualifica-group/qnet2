import { keepPreviousData, useInfiniteQuery } from '@tanstack/react-query'
import { fetchNotes, NOTES_DEFAULT_PAGE_SIZE } from '@/features/notes/api'
import { notesKeys } from '@/features/notes/query-keys'
import type { NoteQuoteScope } from '@/features/notes/types'

/**
 * Infinite-scroll feed of a host record's root notes (spec 0052, D-13),
 * mirroring `useActivityLog`'s keyset pattern. The next page param is the
 * opaque cursor the backend returns as `meta.next_cursor`; `getNextPageParam`
 * returns `undefined` once `meta.has_more` is false, which is what sets
 * TanStack Query's `hasNextPage` to `false`.
 */
export function useNotes(
  entityType: string,
  entityId: number,
  enabled = true,
  quoteScope: NoteQuoteScope = 'all',
) {
  return useInfiniteQuery({
    queryKey: notesKeys.list(entityType, entityId, quoteScope),
    queryFn: ({ pageParam }) =>
      fetchNotes({ entityType, entityId, cursor: pageParam, limit: NOTES_DEFAULT_PAGE_SIZE, quoteScope }),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => (lastPage.meta.has_more ? (lastPage.meta.next_cursor ?? undefined) : undefined),
    // Cambiare `quoteScope` cambia la query key: senza questo la pagina
    // tornerebbe a `undefined` durante il fetch e la sezione smonterebbe i
    // selettori (che vivono in `meta.quotes`) proprio mentre l'utente ci sta
    // interagendo. Tenendo la pagina precedente il filtro resta al suo posto e
    // la lista si sostituisce, non lampeggia. Stesso pattern di `useGeo`.
    placeholderData: keepPreviousData,
    enabled,
  })
}
