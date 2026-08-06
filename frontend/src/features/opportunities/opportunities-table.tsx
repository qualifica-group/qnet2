import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { MessageSquare, Paperclip, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { opportunityColumnRenderers } from '@/features/opportunities/column-renderers'
import { OpportunityQuotesDetailRenderer } from '@/features/opportunities/opportunity-quotes-detail-renderer'
import {
  deleteOpportunity,
  OPPORTUNITIES_DOMAIN,
  OPPORTUNITY_ATTACHABLE_ALIAS,
} from '@/features/opportunities/api'

/**
 * Domain icon overrides for the 'documents'/'notes' row actions: the backend
 * action catalog fixes their icon keys as 'paperclip'/'message-square',
 * absent from the shared defaults in `action-icon-map.ts`. Hoisted at module
 * level (not inline in JSX), mirroring `REQUEST_MANAGEMENT_ACTION_ICONS`, so
 * its identity stays stable across renders.
 */
const OPPORTUNITIES_ACTION_ICONS: ActionIconMap = {
  paperclip: Paperclip,
  'message-square': MessageSquare,
}

/**
 * Thin Opportunities adapter over the generic table (spec 0040, mirrors
 * Leads). It mounts `<TableView>` with the `opportunities` domain, its custom
 * cell renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`, resolved
 * from the user's preference (spec 0042). It still owns the delete flow
 * (confirm + toast + grid refresh) and refreshes the SSRM grid after every
 * mutation. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 *
 * Row actions mirror Gestione Richieste exactly (user directive 2026-08-05):
 * `view`, `documents`, `notes` inline, `delete`/`activity` in the overflow —
 * `edit` is NOT among them, the detail surface owns the Edit button
 * (`detailOwnsEditAction`, still gated by `opportunities.update`). The `notes`
 * action opens the agnostic `NotesDialog` on the SAME thread the detail view
 * mounts: the notes registry maps the Opportunity record under the
 * `request-management` entity_type, so that slug — not `opportunities` — is
 * what the dialog must pass.
 */
export function OpportunitiesTable() {
  const { t } = useTranslation()
  // The expandable Offerte panel (master/detail) is an affordance only: without
  // `quotes.viewAny` the expand chevron is not rendered at all, and the panel's
  // own requests would be refused server-side anyway.
  const { can } = useAbilities()
  const canViewQuotes = can('quotes.viewAny')
  const stats = useStatsPanel(OPPORTUNITIES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [documentsRowId, setDocumentsRowId] = useState<number | null>(null)
  const [notesRowId, setNotesRowId] = useState<number | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `OpportunityFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, sheet } = useModuleOpener(OPPORTUNITIES_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteOpportunity(row.id)
        toast.success(t('opportunities.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('opportunities.form.deleteForbidden'))
        } else {
          toast.error(t('opportunities.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, invalidateStats],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'documents':
          setDocumentsRowId(row.id)
          break
        case 'notes':
          setNotesRowId(row.id)
          break
        default:
          break
      }
    },
    [openView, runDelete],
  )

  // Documents are edited from inside the dialog (upload/delete); refresh the
  // grid on close so the row's `documents_count` badge reflects the change.
  const handleDocumentsOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setDocumentsRowId(null)
        refreshGrid()
      }
    },
    [refreshGrid],
  )

  // Notes are added/deleted from inside the dialog; refresh the grid on close
  // so the row's `notes_count` badge reflects the change (mirrors documents).
  const handleNotesOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setNotesRowId(null)
        refreshGrid()
      }
    },
    [refreshGrid],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={OPPORTUNITIES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="opportunities.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('opportunities.form.newOpportunity')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={OPPORTUNITIES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={OPPORTUNITIES_DOMAIN}
        renderers={opportunityColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        iconMap={OPPORTUNITIES_ACTION_ICONS}
        masterDetail={canViewQuotes}
        detailCellRenderer={OpportunityQuotesDetailRenderer}
        detailRowAutoHeight
      />

      {sheet}

      <ResourceActivityDialog
        resource={OPPORTUNITIES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />

      <DocumentsDialog
        resource={OPPORTUNITY_ATTACHABLE_ALIAS}
        id={documentsRowId}
        onOpenChange={handleDocumentsOpenChange}
      />

      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesRowId}
        onOpenChange={handleNotesOpenChange}
      />
    </div>
  )
}
