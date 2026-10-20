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
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { deleteStaffCourse } from '@/features/staff-courses/api'
import { STAFF_COURSES_DOMAIN } from '@/features/staff-courses/types'

const FORBIDDEN_STATUS = 403
const UNPROCESSABLE_STATUS = 422

/**
 * Thin staff-courses adapter over the generic table (domain `staff-courses`). Open mode of
 * view/create is delegated to `useModuleOpener`; it owns the delete flow (confirm is asked by
 * the table; 422 = trainings already use the course).
 */
export function StaffCoursesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(STAFF_COURSES_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteStaffCourse(Number(row.id))
        toast.success(t('staffCourses.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === FORBIDDEN_STATUS) {
          toast.error(t('staffCourses.form.deleteForbidden'))
        } else if (status === UNPROCESSABLE_STATUS) {
          toast.error(t('staffCourses.form.deleteInUse'))
        } else {
          toast.error(t('staffCourses.form.deleteError'))
        }
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
    [openView, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="staff-courses.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('staffCourses.form.newCourse')}
            </Button>
          </Can>
        }
      />

      <TableView ref={tableRef} domain={STAFF_COURSES_DOMAIN} onAction={handleAction} isBusy={isBusy} />

      {sheet}

      <ResourceActivityDialog
        resource={STAFF_COURSES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
