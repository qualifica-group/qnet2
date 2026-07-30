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
import { leadColumnRenderers } from '@/features/leads/column-renderers'
import { deleteLead } from '@/features/leads/api'
import { useLeadConversion } from '@/features/leads/use-lead-conversion'
import {
  AssignOperatorsDialog,
  type AssignOperatorsDialogInput,
  type AssignOperatorsDialogSite,
} from '@/features/leads/assign-operators-dialog'
import { useAssignOperators } from '@/features/leads/use-assign-operators'
import { ConvertLeadsDialog } from '@/features/leads/convert-leads-dialog'
import type { LeadOperationalSiteRef } from '@/features/leads/types'

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
 * The Sede to precompile in the "Assegna operatori" popup (spec 0048 AC-031):
 * present only when every selected row's `operational_site` (the same shape
 * `LeadsTableDefinition`/`LeadOperationalSiteColumn` project onto the grid
 * row, `{id, label}` or `null`) shares one non-null id. Any null or mismatch
 * across the selection leaves the popup's Sede unset, same as today.
 */
function resolveSharedOperationalSite(rows: TableRow[]): LeadOperationalSiteRef | null {
  const [first, ...rest] = rows.map((row) => row.operational_site as LeadOperationalSiteRef | null)
  if (!first) {
    return null
  }
  return rest.every((site) => site?.id === first.id) ? first : null
}

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

  // Bulk operator assignment (spec 0048 AC-041): the shared popup collects
  // Sede + mode + operator; this adapter owns the selection, the mutation and
  // its own success/error feedback.
  const [assignOpen, setAssignOpen] = useState(false)
  const [assignIds, setAssignIds] = useState<number[]>([])
  const [assignDefaultSite, setAssignDefaultSite] = useState<AssignOperatorsDialogSite | null>(null)
  const canAssignOperators = can('leads.update')

  const assignMutation = useAssignOperators({
    onSuccess: (result) => {
      toast.success(t('leads.assign.success', { count: result.assigned }))
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
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 422 && input.mode === 'balanced'
            ? t('leads.assign.errors.noOperators')
            : t('leads.assign.errors.generic'),
        )
        throw error
      }
    },
    [assignMutation, assignIds, t],
  )

  // AC-031: precompiles the popup's Sede when every selected row shares one.
  const openAssignDialog = useCallback((selection: TableSelection) => {
    setAssignIds(selection.ids)
    setAssignDefaultSite(resolveSharedOperationalSite(selection.rows))
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
        defaultSite={assignDefaultSite}
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
