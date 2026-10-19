import { useCallback, type RefObject } from 'react'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import { STAFF_TRAININGS_DOMAIN } from '@/features/staff-trainings/types'

interface StaffTrainingRegisterProps {
  tableRef: RefObject<TableViewHandle | null>
  /** Opens the detail of the clicked training: the abilities live in the detail. */
  onOpen: (id: number) => void
}

/** The "Registro" tab: the backend-driven grid of the trainings the actor may see. */
export function StaffTrainingRegister({ tableRef, onOpen }: StaffTrainingRegisterProps) {
  const handleAction: RowActionHandler = useCallback((_action, row) => onOpen(Number(row.id)), [onOpen])

  return <TableView ref={tableRef} domain={STAFF_TRAININGS_DOMAIN} onAction={handleAction} inlineAllActions />
}
