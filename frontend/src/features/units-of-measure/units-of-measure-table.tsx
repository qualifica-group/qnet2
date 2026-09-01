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
import { unitOfMeasureColumnRenderers } from '@/features/units-of-measure/column-renderers'
import { deleteUnitOfMeasure } from '@/features/units-of-measure/api'

/** Domain key used to mount the generic table for units of measure. */
const UNITS_OF_MEASURE_DOMAIN = 'units-of-measure'

/**
 * Thin Units of Measure adapter over the generic table. It mounts
 * `<TableView>` with the `units-of-measure` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`,
 * resolved from the user's preference (spec 0042). It still owns the delete
 * flow (confirm + toast + grid refresh, mapping the backend's restrictive
 * delete 409 when the unit is still used by a product or a quote line, D-7)
 * and refreshes the SSRM grid after every mutation. Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function UnitsOfMeasureTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(UNITS_OF_MEASURE_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteUnitOfMeasure(row.id)
        toast.success(t('unitsOfMeasure.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('unitsOfMeasure.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(t('unitsOfMeasure.form.deleteInUse'))
        } else {
          toast.error(t('unitsOfMeasure.form.deleteError'))
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

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="units-of-measure.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('unitsOfMeasure.form.newUnitOfMeasure')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={UNITS_OF_MEASURE_DOMAIN}
        renderers={unitOfMeasureColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={UNITS_OF_MEASURE_DOMAIN}
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
