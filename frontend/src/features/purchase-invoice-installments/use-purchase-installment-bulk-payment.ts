import { useCallback, useState, type RefObject } from 'react'
import { HandCoins } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAbilities } from '@/features/auth/use-abilities'
import {
  BULK_PAYMENT_GROUP_COLUMN,
  PAY_PERMISSION,
  hasSingleSupplier,
  toBulkPaymentTargets,
  type BulkPaymentTarget,
} from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment'
import type { TableViewHandle } from '@/features/table/table-view'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

const NO_TARGETS: readonly BulkPaymentTarget[] = []

/**
 * Selection-driven bulk payment of the payable installments table (spec 0211
 * D-8): the checkbox column exists only while the grid is grouped by supplier
 * and the actor may pay; the action opens the dialog on the selected rows.
 */
export function usePurchaseInstallmentBulkPayment(tableRef: RefObject<TableViewHandle | null>, onChanged: () => void) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [groupedBySupplier, setGroupedBySupplier] = useState(false)
  const [targets, setTargets] = useState<readonly BulkPaymentTarget[]>(NO_TARGETS)
  const enabled = groupedBySupplier && can(PAY_PERMISSION)

  // A regrouping reloads every row: a selection made under the previous grouping no longer applies.
  const onRowGroupColumnsChange = useCallback(
    (columnIds: string[]) => {
      setGroupedBySupplier(columnIds.includes(BULK_PAYMENT_GROUP_COLUMN))
      tableRef.current?.clearSelection()
    },
    [tableRef],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const single = hasSingleSupplier(selection.rows)
      return [
        {
          key: 'bulk_pay',
          label: t(
            single
              ? 'purchaseInvoiceInstallments.bulkPayment.action'
              : 'purchaseInvoiceInstallments.bulkPayment.mixedSuppliers',
          ),
          icon: HandCoins,
          disabled: !single,
          onSelect: () => setTargets(toBulkPaymentTargets(selection.rows)),
        },
      ]
    },
    [t],
  )

  const onSaved = useCallback(() => {
    tableRef.current?.clearSelection()
    onChanged()
  }, [onChanged, tableRef])

  return {
    getBulkActions: enabled ? getBulkActions : undefined,
    onRowGroupColumnsChange,
    targets,
    close: useCallback(() => setTargets(NO_TARGETS), []),
    onSaved,
  }
}
