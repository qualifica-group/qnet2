import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { ArrowRightLeft, FileUp, Plus, UserCog } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useAssignmentScope } from '@/features/assignment/use-assignment-scope'
import { leadColumnRenderers } from '@/features/leads/column-renderers'
import { deleteLead } from '@/features/leads/api'
import { resolveAssignFeedback } from '@/features/leads/assign-feedback'
import { useLeadConversion } from '@/features/leads/use-lead-conversion'
import {
  AssignOperatorsDialog,
  type AssignOperatorsDialogInput,
} from '@/features/leads/assign-operators-dialog'
import { useAssignOperators } from '@/features/leads/use-assign-operators'
import { ConvertLeadsDialog } from '@/features/leads/convert-leads-dialog'

/** Domain key used to mount the generic table for leads. */
const LEADS_DOMAIN = 'leads'

/**
 * Domain icon override for the 'convert_to_opportunity' row action (spec
 * 0044): the backend action catalog fixes the icon key as 'arrow-right-left',
 * absent from the shared defaults in `action-icon-map.ts`. Hoisted at module
 * level (not inline in JSX) so its identity stays stable across renders — it
 * flows into `TableView`'s internal `useMemo` dependency list.
 */
const LEADS_ACTION_ICONS: ActionIconMap = { 'arrow-right-left': ArrowRightLeft }

/**
 * Thin Leads adapter over the generic table. It mounts `<TableView>` with the
 * `leads` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow (confirm + toast + grid refresh), the "Import"
 * navigation, and refreshes the SSRM grid after every mutation. Permission
 * gating is an affordance only; the backend re-authorizes each call.
 */
export function LeadsTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { can } = useAbilities()
  const stats = useStatsPanel(LEADS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(LEADS_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `LeadFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(LEADS_DOMAIN, { onSaved })

  // Lead -> opportunity conversion controller (spec 0044, revised;
  // directive 2026-07-21 dropped the correction gate): opens the prefilled
  // Opportunity form directly. Reuses the same `onSaved` (grid refresh +
  // stats invalidation) as the leads opener above.
  const { startConversion, sheets: conversionSheets } = useLeadConversion({
    onOpportunitySaved: onSaved,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteLead(row.id)
        toast.success(t('leads.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('leads.form.deleteForbidden'))
        } else {
          toast.error(t('leads.form.deleteError'))
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
        case 'edit':
          openEdit(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'convert_to_opportunity':
          startConversion(row.id)
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete, startConversion],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  // Bulk operator assignment (spec 0048 AC-041): the shared popup collects the
  // mode and, for `single`, the operator; this adapter owns the selection, the
  // mutation and its own success/error feedback.
  const [assignOpen, setAssignOpen] = useState(false)
  const [assignIds, setAssignIds] = useState<number[]>([])
  const canAssignOperators = can('leads.update')

  // Scope of the popup's Operatore picker (spec 0110 AC-041, spec 0113): the
  // categories the selection requires AND the Sede its campaigns share,
  // resolved here rather than in the dialog, which stays domain-agnostic. The
  // Sede only narrows the picker — the server recomputes it per lead. Gated on
  // the popup being open so a selection alone never issues the request.
  const { competenceCategoryIds, operationalSiteId, isResolving } = useAssignmentScope({
    selection: assignIds.length > 0 ? { domain: 'leads', ids: assignIds } : null,
    enabled: assignOpen,
  })

  const assignMutation = useAssignOperators({
    onSuccess: (result) => {
      toast.success(resolveAssignFeedback(t, 'leads.assign', result))
      refreshGrid()
      tableRef.current?.clearSelection()
      invalidateStats()
    },
  })

  const handleAssign = useCallback(
    async (input: AssignOperatorsDialogInput) => {
      try {
        await assignMutation.mutateAsync({ lead_ids: assignIds, ...input })
      } catch (error) {
        // Spec 0113 removed the "this Sede has no operators" 422: a record with no
        // candidate is now reported as `skipped` inside a 200. Any 422 still reaching
        // here has a different cause, so naming that one would point at the wrong thing.
        toast.error(t('leads.assign.errors.generic'))
        throw error
      }
    },
    [assignMutation, assignIds, t],
  )

  const openAssignDialog = useCallback((selection: TableSelection) => {
    setAssignIds(selection.ids)
    setAssignOpen(true)
  }, [])

  // Mass lead -> opportunity conversion (spec 0071). Unlike the single row
  // action, which opens the prefilled Opportunity form, the batch derives
  // everything server-side; the popup owns the confirmation and the blocker
  // reporting, this adapter the selection and the post-success refresh.
  const [convertOpen, setConvertOpen] = useState(false)
  const [convertRows, setConvertRows] = useState<TableRow[]>([])
  const canCreateOpportunities = can('opportunities.create')

  const openConvertDialog = useCallback((selection: TableSelection) => {
    setConvertRows(selection.rows)
    setConvertOpen(true)
  }, [])

  const handleConverted = useCallback(
    (converted: number) => {
      toast.success(t('leads.bulkConvert.success', { count: converted }))
      refreshGrid()
      tableRef.current?.clearSelection()
      invalidateStats()
    },
    [t, refreshGrid, invalidateStats],
  )

  // Surfaced inside the generic table's single "Actions" dropdown, each entry
  // gated on its own ability. `undefined` (not a function returning an empty
  // array) when the actor has neither, so the checkbox column stays off
  // entirely rather than offering a menu with no reachable bulk action.
  const getBulkActions =
    canAssignOperators || canCreateOpportunities
      ? (selection: TableSelection): BulkAction[] => [
          ...(canAssignOperators
            ? [
                {
                  key: 'assign-operators',
                  label: t('leads.assign.tableButton'),
                  icon: UserCog,
                  onSelect: () => openAssignDialog(selection),
                },
              ]
            : []),
          ...(canCreateOpportunities
            ? [
                {
                  key: 'convert-to-opportunities',
                  label: t('leads.bulkConvert.tableButton'),
                  icon: ArrowRightLeft,
                  onSelect: () => openConvertDialog(selection),
                },
              ]
            : []),
        ]
      : undefined

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={LEADS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="leads.import">
              <Button variant="outline" className="bg-white" onClick={() => void navigate('/imports')}>
                <FileUp aria-hidden="true" />
                {t('leads.form.importLeads')}
              </Button>
            </Can>
            <Can permission="leads.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('leads.form.newLead')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={LEADS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={LEADS_DOMAIN}
        renderers={leadColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        iconMap={LEADS_ACTION_ICONS}
        getBulkActions={getBulkActions}
      />

      <AssignOperatorsDialog
        open={assignOpen}
        onOpenChange={setAssignOpen}
        selectionCount={assignIds.length}
        operatorSiteId={operationalSiteId}
        competenceCategoryIds={competenceCategoryIds}
        isResolvingCompetence={isResolving}
        onAssign={handleAssign}
      />

      <ConvertLeadsDialog
        open={convertOpen}
        onOpenChange={setConvertOpen}
        rows={convertRows}
        onConverted={handleConverted}
      />

      {sheet}
      {conversionSheets}

      <ResourceActivityDialog
        resource={LEADS_DOMAIN}
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
