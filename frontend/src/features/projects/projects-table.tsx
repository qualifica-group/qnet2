import { forwardRef, useCallback, useImperativeHandle, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { projectColumnRenderers } from '@/features/projects/column-renderers'
import { deleteProject } from '@/features/projects/api'

/** Domain key used to mount the generic table for projects. */
const PROJECTS_DOMAIN = 'projects'

interface ProjectsTableProps {
  /**
   * Skips this component's own `PageHeader`/"New project" button (default
   * `false`, so any other caller keeps the current self-contained behaviour
   * unchanged). `ProjectsView` (spec 0026 layout fix) sets this to `true`: it
   * owns a single, unified `PageHeader` shared with the card-grid view, so
   * this adapter must not render a second one.
   */
  hideHeader?: boolean
}

/** Imperative handle so a caller that hides this component's own header (`hideHeader`) can still refresh its grid after a mutation it triggered itself. */
export interface ProjectsTableHandle {
  refresh: () => void
}

/**
 * Thin Projects adapter over the generic table. It mounts `<TableView>` with
 * the `projects` domain, its custom cell renderers and a row-action handler,
 * and delegates the open mode (modal Sheet vs dedicated page) of view/edit/
 * create to `useModuleOpener`, resolved from the user's preference (spec 0042).
 * It still owns the delete flow (confirm + toast + grid refresh) and the SSRM
 * grid refresh after every mutation via the table's imperative handle.
 * Permission gating is an affordance only; the backend re-authorizes each call.
 */
export const ProjectsTable = forwardRef<ProjectsTableHandle, ProjectsTableProps>(
  function ProjectsTable({ hideHeader = false }, forwardedRef) {
    const { t } = useTranslation()
    const invalidateStats = useInvalidateModuleStats(PROJECTS_DOMAIN)

    const tableRef = useRef<TableViewHandle>(null)
    const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

    useImperativeHandle(forwardedRef, () => ({ refresh: refreshGrid }), [refreshGrid])

    const [deletingId, setDeletingId] = useState<number | null>(null)
    const [activityRow, setActivityRow] = useState<TableRow | null>(null)

    // After a modal create/edit succeeds the Sheet closes itself; the grid and
    // the stats panel are this adapter's to refresh. The detail query is
    // invalidated inside `ProjectFormScreen`. Page mode never calls this.
    const onSaved = useCallback(() => {
      refreshGrid()
      invalidateStats()
    }, [refreshGrid, invalidateStats])

    const { openCreate, openView, openEdit, openDuplicate, sheet } = useModuleOpener(PROJECTS_DOMAIN, {
      onSaved,
    })

    const runDelete = useCallback(
      async (row: TableRow) => {
        setDeletingId(Number(row.id))
        try {
          await deleteProject(Number(row.id))
          toast.success(t('projects.form.deleted'))
          refreshGrid()
          invalidateStats()
        } catch (error) {
          const status = axios.isAxiosError(error) ? error.response?.status : undefined
          if (status === 403) {
            toast.error(t('projects.form.deleteForbidden'))
          } else if (status === 409) {
            toast.error(t('projects.form.deleteHasCampaigns'))
          } else {
            toast.error(t('projects.form.deleteError'))
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
          case 'duplicate':
            openDuplicate(row)
            break
          case 'delete':
            void runDelete(row)
            break
          case 'activity':
            setActivityRow(row)
            break
          default:
            break
        }
      },
      [openView, openEdit, openDuplicate, runDelete],
    )

    const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

    return (
      <div className="flex flex-1 flex-col gap-4">
        {hideHeader ? null : (
          <PageHeader
            actions={
              <Can permission="projects.create">
                <Button onClick={openCreate}>
                  <Plus aria-hidden="true" />
                  {t('projects.form.newProject')}
                </Button>
              </Can>
            }
          />
        )}

        <TableView
          ref={tableRef}
          domain={PROJECTS_DOMAIN}
          renderers={projectColumnRenderers}
          onAction={handleAction}
          isBusy={isBusy}
        />

        {sheet}

        <ResourceActivityDialog
          resource={PROJECTS_DOMAIN}
          row={activityRow}
          onOpenChange={(open) => {
            if (!open) {
              setActivityRow(null)
            }
          }}
        />
      </div>
    )
  },
)
