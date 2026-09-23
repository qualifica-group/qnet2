import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type {
  TableActionDefinition,
  TableRow,
} from '@/features/table/types'
import { roleColumnRenderers } from '@/features/roles/column-renderers'
import { deleteRole } from '@/features/roles/api'

/** Domain key used to mount the generic table for roles. */
const ROLES_DOMAIN = 'roles'

/** The privileged system role that can never be edited or deleted. */
const SYSTEM_ROLE = 'super-admin'

/**
 * Thin Roles adapter over the generic table. It mounts `<TableView>` with the
 * `roles` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow (confirm + toast + grid refresh) and the SSRM
 * grid refresh after every mutation. Permission gating is an affordance only;
 * the backend re-authorizes each call.
 */
export function RolesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid is
  // this adapter's to refresh. The detail query is invalidated inside
  // `RoleFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
  }, [refreshGrid])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(ROLES_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteRole(Number(row.id))
        toast.success(t('roles.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('roles.form.deleteForbidden')
            : t('roles.form.deleteError'),
        )
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
        default:
          break
      }
    },
    [openView, openEdit, runDelete],
  )

  // Hide edit/delete on the protected super-admin system role (mutations are
  // forbidden server-side; this avoids offering dead-end actions). The backend
  // already omits them, this is a belt-and-braces affordance.
  const decorateRow = useCallback((row: TableRow): TableRow => {
    if (row.name === SYSTEM_ROLE) {
      return {
        ...row,
        actions: row.actions.filter((key) => key !== 'edit' && key !== 'delete'),
      }
    }
    return row
  }, [])

  const isBusy = useCallback(
    (row: TableRow) => row.id === deletingId,
    [deletingId],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="roles.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('roles.form.newRole')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={ROLES_DOMAIN}
        renderers={roleColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        decorateRow={decorateRow}
      />

      {sheet}

      <ResourceActivityDialog
        resource={ROLES_DOMAIN}
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
