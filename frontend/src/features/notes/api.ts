import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { PaginatedResponse } from '@/features/for-select/types'
import type {
  CreateNotePayload,
  MentionableUser,
  MentionScope,
  Note,
  NoteMutationResult,
  NoteQuoteScope,
  NotesPage,
  NotesPageMeta,
  UpdateNotePayload,
} from '@/features/notes/types'

/** Default page size requested when the caller does not override it (counts roots, D-13). */
export const NOTES_DEFAULT_PAGE_SIZE = 20

/** Default page size requested per mentionable-users fetch (mirrors `FOR_SELECT_PAGE_SIZE`). */
export const NOTES_MENTIONABLE_PAGE_SIZE = 25

interface FetchNotesParams {
  entityType: string
  entityId: number
  cursor?: string | null
  limit?: number
  /** Spec 0085 D-2: `'all'` (default) | `'general'` | id di un'Offerta. */
  quoteScope?: NoteQuoteScope
}

/**
 * Fetches one keyset page of root notes (with their full reply thread, D-7)
 * for a host record. `cursor` is the opaque token from the previous page's
 * `meta.next_cursor`; omit it for the first page.
 */
export async function fetchNotes({
  entityType,
  entityId,
  cursor = null,
  limit = NOTES_DEFAULT_PAGE_SIZE,
  quoteScope = 'all',
}: FetchNotesParams): Promise<NotesPage> {
  const { data } = await apiClient.get<ApiResponse<Note[]> & { meta: NotesPageMeta }>('/notes', {
    params: {
      entity_type: entityType,
      entity_id: entityId,
      cursor: cursor ?? undefined,
      limit,
      // Spec 0085 D-2: un solo parametro. `'all'` e' il default anche
      // server-side, quindi non lo si manda: tenere l'URL minimo evita che due
      // chiamate equivalenti finiscano in cache HTTP diverse.
      quote_scope: quoteScope === 'all' ? undefined : String(quoteScope),
    },
  })
  return { data: data.data, meta: data.meta }
}

/** Envelope di create/update (spec 0353): la nota e, in `meta`, gli id aggiunti al record dalle menzioni. */
type NoteMutationEnvelope = ApiResponse<Note> & { meta?: { enlisted_user_ids?: number[] } }

function toMutationResult(envelope: NoteMutationEnvelope): NoteMutationResult {
  return { note: envelope.data, enlistedUserIds: envelope.meta?.enlisted_user_ids ?? [] }
}

/** Creates a root note or a reply (`parent_id`, normalized server-side to the thread root). */
export async function createNote(payload: CreateNotePayload): Promise<NoteMutationResult> {
  const { data } = await apiClient.post<NoteMutationEnvelope>('/notes', payload)
  return toMutationResult(data)
}

/** Edits a note's own body/mentions (author-only, enforced server-side). Sets `edited_at`. */
export async function updateNote(noteId: number, payload: UpdateNotePayload): Promise<NoteMutationResult> {
  const { data } = await apiClient.patch<NoteMutationEnvelope>(`/notes/${noteId}`, payload)
  return toMutationResult(data)
}

/** Soft-deletes a note (author-only, enforced server-side). Replies of a deleted root are hidden, not removed. */
export async function deleteNote(noteId: number): Promise<void> {
  await apiClient.delete<ApiResponse<null>>(`/notes/${noteId}`)
}

interface FetchMentionableUsersParams {
  entityType: string
  entityId: number
  search?: string
  offset?: number
  limit?: number
  /** Ids of already-selected mentions to hydrate on the first page (edit mode). */
  ids?: number[]
  /** Spec 0353: `extended` = tutti gli utenti attivi (solo chi puo' modificare il record). Omesso = perimetro del record. */
  scope?: MentionScope
}

/**
 * Fetches one page of the CONTEXTUAL mention lookup of a host record (D-10):
 * only users who can read that specific record. The response shape is
 * intentionally identical to the for-select contract (ADR 0011,
 * `PaginatedResponse<ForSelectItem>`), but the endpoint is `/notes/mentionable-users`
 * (not `/{resource}/for-select`), so `fetchForSelect` cannot be reused as-is —
 * only its response types are.
 */
export async function fetchMentionableUsers({
  entityType,
  entityId,
  search,
  offset = 0,
  limit = NOTES_MENTIONABLE_PAGE_SIZE,
  ids,
  scope = 'record',
}: FetchMentionableUsersParams): Promise<PaginatedResponse<MentionableUser>> {
  const { data } = await apiClient.get<PaginatedResponse<MentionableUser>>('/notes/mentionable-users', {
    params: {
      entity_type: entityType,
      entity_id: entityId,
      offset,
      limit,
      ...(search ? { search } : {}),
      ...(ids && ids.length > 0 ? { ids } : {}),
      // Il default `record` non viaggia: senza interruttore la richiesta resta identica a prima.
      ...(scope === 'extended' ? { scope } : {}),
    },
    // Serialize `ids` as repeated `ids[]=1&ids[]=2` (Laravel array convention).
    paramsSerializer: { indexes: true },
  })
  return data
}
