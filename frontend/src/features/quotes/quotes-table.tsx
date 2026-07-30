import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { FileText, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { deleteQuote, QUOTES_DOMAIN } from '@/features/quotes/api'
import { useQuoteDocument } from '@/features/quotes/use-quote-document'

/**
 * Domain icon override for the 'generate_document' row action (spec 0070):
 * the backend action catalog fixes the icon key as 'file-text', absent from
 * the shared defaults in `action-icon-map.ts`. Hoisted at module level
 * (mirrors `OPPORTUNITIES_ACTION_ICONS`), so its identity stays stable.
 */
const QUOTES_ACTION_ICONS: ActionIconMap = { 'file-text': FileText }

/** Reads a row's `code` column defensively (schema-driven values are loosely typed), falling back to the numeric id. */
function resolveRowCode(row: TableRow): string {
  return typeof row.code === 'string' ? row.code : String(row.id)
}

/**
 * Thin Quotes adapter over the generic table (spec 0065, mirrors
 * `OpportunitiesTable`/`ProductsTable`). It mounts `<TableView>` with the
 * `quotes` domain (AC-078: no columns declared client-side), its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`, resolved
 * from the user's preference (spec 0042). It still owns the delete flow
 * (confirm + toast + grid refresh) and refreshes the SSRM grid after every
 * mutation. No stats panel, no documents dialog (both explicitly out of
 * scope, spec 0065 `<out>`). Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function QuotesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(QUOTES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const { generate: generateDocument, isGenerating } = useQuoteDocument()

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteQuote(row.id)
        toast.success(t('quotes.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(status === 403 ? t('quotes.form.deleteForbidden') : t('quotes.form.deleteError'))
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
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
        case 'generate_document':
          // Not a mutation (D-2): no `refreshGrid()`, the row is unchanged
          // (AC-302).
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

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="quotes.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('quotes.form.newQuote')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={QUOTES_DOMAIN}
        renderers={quoteColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        iconMap={QUOTES_ACTION_ICONS}
      />

      {sheet}

      <ResourceActivityDialog
        resource={QUOTES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}
