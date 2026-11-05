import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { forbiddenMessage } from '@/lib/forbidden-message'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { deleteTreasuryMovement } from '@/features/treasury-movements/api'
import { TreasuryMovementDialog } from '@/features/treasury-movements/treasury-movement-dialog'
import { TREASURY_MOVEMENTS_DOMAIN } from '@/features/treasury-movements/types'

/**
 * Thin adapter over the generic table for the `treasury-movements` domain. The
 * create/edit form is a modal; delete is confirmed by the table itself. Gating
 * by permission is an affordance only: the backend re-authorizes each call.
 */
export function TreasuryMovementsTable() {
  const { t, i18n } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [target, setTarget] = useState<{ id: number | null } | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteTreasuryMovement(Number(row.id))
        toast.success(t('treasuryMovements.form.deleted'))
        refreshGrid()
      } catch (error) {
        toast.error(forbiddenMessage(error, i18n) ?? serverMessage(error) ?? t('treasuryMovements.form.deleteError'))
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, i18n],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
        case 'edit':
          setTarget({ id: Number(row.id) })
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
    [runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="treasury-movements.create">
            <Button onClick={() => setTarget({ id: null })}>
              <Plus aria-hidden="true" />
              {t('treasuryMovements.form.newMovement')}
            </Button>
          </Can>
        }
      />

      <TableView ref={tableRef} domain={TREASURY_MOVEMENTS_DOMAIN} onAction={handleAction} isBusy={isBusy} />

      <TreasuryMovementDialog
        target={target}
        onClose={() => setTarget(null)}
        onSaved={() => {
          setTarget(null)
          refreshGrid()
        }}
      />

      <ResourceActivityDialog
        resource={TREASURY_MOVEMENTS_DOMAIN}
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
