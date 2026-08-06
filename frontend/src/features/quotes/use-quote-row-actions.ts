import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import { deleteQuote, QUOTES_DOMAIN } from '@/features/quotes/api'
import { useQuoteDocument } from '@/features/quotes/use-quote-document'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

export interface UseQuoteRowActionsOptions {
  /**
   * Called after anything that changes the displayed rows: a successful
   * create/edit save, or a delete. The caller decides what "refresh" means for
   * its own surface (purge an SSRM cache, invalidate a query).
   */
  onMutated: () => void
  /**
   * Forces the open mode of view/edit/create instead of honoring the user's
   * preference (spec 0067 D-3): an EMBEDDED surface must never navigate away
   * from the record hosting it. Omitted, the actor's own preference wins.
   */
  forceMode?: OpenMode
}

/**
 * Everything the `notes` dialog needs about the Offerta it was opened from
 * (spec 0085): the note lives on the PARENT Opportunity's thread
 * (`opportunityId` = the notes `entity_id`) scoped to this Offerta
 * (`quoteId`), and `code` labels the dialog. Resolved once here so both
 * surfaces mount the dialog from the same shape.
 */
export interface QuoteNotesTarget {
  opportunityId: number
  quoteId: number
  code: string
}

export interface UseQuoteRowActionsResult {
  handleAction: RowActionHandler
  isBusy: (row: TableRow) => boolean
  activityRow: TableRow | null
  closeActivity: (open: boolean) => void
  /** The Offerta whose notes are open, `null` when the dialog is closed. */
  notesTarget: QuoteNotesTarget | null
  closeNotes: (open: boolean) => void
  sheet: ReactNode
  /** Opens an empty create form (safe to bind straight to an `onClick`). */
  openCreate: () => void
  /** Opens the create form pre-linked to a parent record (e.g. `{ opportunity_id }`). */
  openCreateWith: (params: ModuleCreateParams) => void
}

/** Reads a row's `code` column defensively (schema-driven values are loosely typed), falling back to the numeric id. */
function resolveRowCode(row: TableRow): string {
  return typeof row.code === 'string' ? row.code : String(row.id)
}

/**
 * Reads the parent Opportunity id off the row's `opportunity` relation ref
 * (`{ id, name }`, projected by QuotesTableDefinition::mapRow). Defensive like
 * `resolveRowCode`: schema-driven row values are `unknown` at this boundary,
 * and a row without the column must simply not open the dialog.
 */
function resolveOpportunityId(row: TableRow): number | null {
  const opportunity = row.opportunity
  if (opportunity === null || typeof opportunity !== 'object') {
    return null
  }
  const { id } = opportunity as { id?: unknown }

  return typeof id === 'number' ? id : null
}

/**
 * The Quotes action catalog's BEHAVIOR, owned once and shared by every surface
 * that renders those actions: the standalone Offerte grid (`QuotesTable`) and
 * the Offerte panel expanded inside an Opportunity row
 * (`OpportunityQuotesDetailRenderer`). Same keys, same flows, same toasts —
 * so the two can never drift apart (user directive 2026-08-06: "le azioni del
 * drop-down devono essere sincronizzate con quelle su Offerte").
 *
 * Holds no rendering: the activity dialog and the Sheet are returned as
 * state/nodes for the caller to mount, keeping this a plain `.ts` hook.
 */
export function useQuoteRowActions({
  onMutated,
  forceMode,
}: UseQuoteRowActionsOptions): UseQuoteRowActionsResult {
  const { t } = useTranslation()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [notesTarget, setNotesTarget] = useState<QuoteNotesTarget | null>(null)

  const { openCreate, openCreateWith, openView, openEdit, sheet } = useModuleOpener(QUOTES_DOMAIN, {
    onSaved: onMutated,
    forceMode,
  })

  const { generate: generateDocument, isGenerating } = useQuoteDocument()

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteQuote(row.id)
        toast.success(t('quotes.form.deleted'))
        onMutated()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(status === 403 ? t('quotes.form.deleteForbidden') : t('quotes.form.deleteError'))
      } finally {
        setDeletingId(null)
      }
    },
    [onMutated, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'edit':
          openEdit(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'notes': {
          // Spec 0085: la nota resta appesa all'Opportunita' padre e viene
          // solo filtrata su `quote_id` — senza il padre non c'e' thread da
          // aprire, quindi l'azione non fa nulla.
          const opportunityId = resolveOpportunityId(row)
          if (opportunityId !== null) {
            setNotesTarget({ opportunityId, quoteId: row.id, code: resolveRowCode(row) })
          }
          break
        }
        case 'generate_document':
          // Not a mutation (spec 0070 D-2): no refresh, the row is unchanged.
          void generateDocument(row.id, resolveRowCode(row))
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete, generateDocument],
  )

  const isBusy = useCallback(
    (row: TableRow) => row.id === deletingId || isGenerating(row.id),
    [deletingId, isGenerating],
  )

  const closeActivity = useCallback((open: boolean) => {
    if (!open) {
      setActivityRow(null)
    }
  }, [])

  // Nessun refresh alla chiusura (a differenza della griglia Opportunita'):
  // le Offerte non portano un badge `notes_count`, quindi nessuna cella della
  // riga dipende da cio' che e' stato scritto nel dialog.
  const closeNotes = useCallback((open: boolean) => {
    if (!open) {
      setNotesTarget(null)
    }
  }, [])

  return {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    notesTarget,
    closeNotes,
    sheet,
    openCreate,
    openCreateWith,
  }
}
