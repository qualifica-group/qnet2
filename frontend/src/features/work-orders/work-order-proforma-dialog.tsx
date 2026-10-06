import type { TableRow } from '@/features/table/types'
import { ProformaRequestDialog } from '@/features/work-orders/proforma-request-dialog'

interface WorkOrderProformaDialogProps {
  /** The work order row whose "€" action was clicked; null = closed. */
  row: TableRow | null
  onClose: () => void
  /** Called after the requests were created, so the host refreshes its grid. */
  onSent: () => void
}

/**
 * Mounts the proforma request modal for the row picked through the "€" row
 * action (spec 0193), shared by the Commesse grid and the Contract detail's
 * Commesse tab. Mounted only while a row is picked, so the grid costs no
 * summary request.
 */
export function WorkOrderProformaDialog({ row, onClose, onSent }: WorkOrderProformaDialogProps) {
  if (row === null || typeof row.id !== 'number') {
    return null
  }

  return (
    <ProformaRequestDialog
      workOrderId={row.id}
      workOrderCode={typeof row.code === 'string' ? row.code : String(row.id)}
      open
      onOpenChange={(open) => {
        if (!open) {
          onClose()
        }
      }}
      onSent={() => {
        onClose()
        onSent()
      }}
    />
  )
}
