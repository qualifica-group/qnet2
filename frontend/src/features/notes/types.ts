/**
 * Domain-agnostic collaborative notes (spec 0052, PARTE B). Mirrors the frozen
 * `Note` shape shared by `GET/POST/PATCH /api/notes`. This feature carries no
 * knowledge of any host module beyond `entityType`/`entityId`, passed in by
 * the caller (D-9, D-14): never import or reference `request-management` here.
 */

/** Author projection embedded in every note (never a full user resource). */
export interface NoteAuthor {
  id: number
  name: string
  avatar_url: string | null
}

/** One user mentioned in a note's `body`, resolved server-side (D-12). */
export interface NoteMention {
  id: number
  name: string
}

/** Per-actor authorization computed server-side for the current user (D-8). */
export interface NotePermissions {
  update: boolean
  delete: boolean
}

/**
 * A note: either a root (`parent_id: null`, carries `replies`) or a reply
 * (`parent_id` set, never carries `replies` — thread is a single level, D-7).
 */
/** L'Offerta a cui una nota e' legata, come esposta da `NoteResource.quote`. */
export interface NoteQuoteRef {
  id: number
  code: string
  title: string
}

/**
 * Il filtro della lista note (spec 0085, D-2): un solo parametro, tre forme.
 * `'all'` = tutte, `'general'` = solo quelle non legate a un'Offerta, un id
 * numerico = solo quelle di quell'Offerta.
 */
export type NoteQuoteScope = 'all' | 'general' | number

export interface Note {
  id: number
  /** Raw body, with mention tokens `@[Name](user:12)` (D-12); resolved to chips by `note-body.tsx`. */
  body: string
  author: NoteAuthor
  /** Order = first appearance in `body`. */
  mentions: NoteMention[]
  parent_id: number | null
  created_at: string
  /** Set only once the body has been edited. */
  edited_at: string | null
  can: NotePermissions
  /**
   * Spec 0085: `null` = nota GENERALE dell'Opportunita', valorizzato = nota di
   * quella singola Offerta. Immutabile dopo la creazione (D-3), e su una reply
   * e' sempre quello della root (D-4).
   */
  quote_id: number | null
  /** Proiezione compatta dell'Offerta per etichettare il contesto in lista; `null` sulle generali. */
  quote: NoteQuoteRef | null
  /** Present only on roots returned by `GET /api/notes`, `created_at` ASC. */
  replies?: Note[]
}

/** Keyset pagination metadata of a notes page (D-13: counts roots, not replies). */
export interface NotesPageMeta {
  next_cursor: string | null
  has_more: boolean
  /**
   * Spec 0085 amendment: le Offerte del record ospite, indipendenti dal
   * filtro applicato — sono le scelte, non il risultato. Opzionale per
   * compatibilita' con le fixture dei test che costruiscono una pagina a
   * mano: assente si comporta come "nessuna Offerta" (nessun selettore).
   */
  quotes?: NoteQuoteRef[]
}

/** One page as returned in the `GET /api/notes` envelope `data` + `meta`. */
export interface NotesPage {
  data: Note[]
  meta: NotesPageMeta
}

/** `POST /api/notes` payload. */
export interface CreateNotePayload {
  /** Spec 0085: assente/null = nota generale. Su una reply e' ignorato: eredita dalla root. */
  quote_id?: number | null
  entity_type: string
  entity_id: number
  body: string
  /** Id of ANY note in the thread; the server normalizes it to the thread's root (D-7). */
  parent_id?: number | null
  /** Must coincide exactly with the ids in the `body` mention tokens (D-12). */
  mentions?: number[]
}

/** `PATCH /api/notes/{note}` payload. `entity_type`/`entity_id`/`parent_id` are not editable. */
export interface UpdateNotePayload {
  body: string
  mentions?: number[]
}
