import { useCallback, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useAuth } from '@/features/auth/use-auth'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type {
  TableActionDefinition,
  TableRow,
} from '@/features/table/types'
import { userColumnRenderers } from '@/features/users/column-renderers'
import { deleteUser } from '@/features/users/api'

/** Domain key used to mount the generic table for users. */
const USERS_DOMAIN = 'users'

/**
 * Thin Users adapter over the generic table. It mounts `<TableView>` with the
 * `users` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow (confirm + toast + grid refresh) and the SSRM
 * grid refresh after every mutation. Permission gating is an affordance only;
 * the backend re-authorizes each call.
 */
export function UsersTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const stats = useStatsPanel(USERS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(USERS_DOMAIN)
  const { user: currentUser, impersonate } = useAuth()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `UserFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(USERS_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteUser(Number(row.id))
        toast.success(t('users.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        // 403 covers self-delete (and any other policy denial) surfaced as a
        // dedicated message; everything else falls back to a generic error.
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('users.form.deleteForbidden')
            : t('users.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, invalidateStats],
  )

  const runImpersonate = useCallback(
    async (row: TableRow) => {
      try {
        await impersonate(Number(row.id))
        navigate('/dashboard')
      } catch {
        toast.error(t('impersonation.startError'))
      }
    },
    [impersonate, navigate, t],
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
        case 'impersonate':
          void runImpersonate(row)
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete, runImpersonate],
  )

  // Hide the delete action on the current user's own row (self-delete is
  // forbidden server-side; this avoids offering a dead-end action).
  const decorateRow = useCallback(
    (row: TableRow): TableRow => {
      if (currentUser && row.id === currentUser.id) {
        return { ...row, actions: row.actions.filter((key) => key !== 'delete') }
      }
      return row
    },
    [currentUser],
  )

  const isBusy = useCallback(
    (row: TableRow) => row.id === deletingId,
    [deletingId],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={USERS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="users.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('users.form.newUser')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={USERS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={USERS_DOMAIN}
        renderers={userColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        decorateRow={decorateRow}
      />

      {sheet}

      <ResourceActivityDialog
        resource={USERS_DOMAIN}
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
