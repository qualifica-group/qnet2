import { useCallback, type RefObject } from 'react'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { RowActionHandler } from '@/features/table/row-actions'
import { StaffTrainingOutcomeBadge } from '@/features/staff-trainings/staff-training-outcome-badge'
import { STAFF_TRAININGS_DOMAIN } from '@/features/staff-trainings/types'

/** The `outcome` column arrives as the raw enum value: it is drawn as the same badge as the detail. */
const RENDERERS: TableRendererMap = {
  outcome: (params) => <StaffTrainingOutcomeBadge outcome={params.value} />,
}

interface StaffTrainingRegisterProps {
  tableRef: RefObject<TableViewHandle | null>
  /** Opens the detail of the clicked training: the abilities live in the detail. */
  onOpen: (id: number) => void
}

/** The "Registro" tab: the backend-driven grid of the trainings the actor may see. */
export function StaffTrainingRegister({ tableRef, onOpen }: StaffTrainingRegisterProps) {
  const handleAction: RowActionHandler = useCallback((_action, row) => onOpen(Number(row.id)), [onOpen])

  return (
    <TableView ref={tableRef} domain={STAFF_TRAININGS_DOMAIN} renderers={RENDERERS} onAction={handleAction} inlineAllActions />
  )
}
