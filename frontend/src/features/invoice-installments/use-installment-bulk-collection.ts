import { useCallback, useState, type RefObject } from 'react'
import { HandCoins } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAbilities } from '@/features/auth/use-abilities'
import {
  BULK_COLLECTION_GROUP_COLUMN,
  hasSingleCustomer,
  toBulkCollectionTargets,
  type BulkCollectionTarget,
} from '@/features/invoice-installments/installment-bulk-collection'
import type { TableViewHandle } from '@/features/table/table-view'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

const NO_TARGETS: readonly BulkCollectionTarget[] = []
const COLLECT_PERMISSION = 'invoices.collect'

/**
 * Selection-driven bulk collection of the Scadenze table (spec 0198): the
 * checkbox column exists only while the grid is grouped by customer and the
 * actor may collect; the action opens the dialog on the selected rows.
 */
export function useInstallmentBulkCollection(tableRef: RefObject<TableViewHandle | null>, onChanged: () => void) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [groupedByCustomer, setGroupedByCustomer] = useState(false)
  const [targets, setTargets] = useState<readonly BulkCollectionTarget[]>(NO_TARGETS)
  const enabled = groupedByCustomer && can(COLLECT_PERMISSION)

  // A regrouping reloads every row: a selection made under the previous grouping no longer applies.
  const onRowGroupColumnsChange = useCallback(
    (columnIds: string[]) => {
      setGroupedByCustomer(columnIds.includes(BULK_COLLECTION_GROUP_COLUMN))
      tableRef.current?.clearSelection()
    },
    [tableRef],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const single = hasSingleCustomer(selection.rows)
      return [
        {
          key: 'bulk_collect',
          label: t(single ? 'invoiceInstallments.bulkCollection.action' : 'invoiceInstallments.bulkCollection.mixedCustomers'),
          icon: HandCoins,
          disabled: !single,
          onSelect: () => setTargets(toBulkCollectionTargets(selection.rows)),
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
