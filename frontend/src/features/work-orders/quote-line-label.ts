import type { ForSelectItem } from '@/features/for-select/types'
import type { WorkOrderQuoteLine } from '@/features/work-orders/types'

/** Separator between a line's product code and name, matching the backend's composed label (D-8). */
const LABEL_SEPARATOR = ' — '

/** Projects a work order's already-loaded pivot line onto the `ForSelectItem` shape the picker hydrates from. */
export function quoteLineToForSelectItem(line: WorkOrderQuoteLine): ForSelectItem {
  return {
    id: line.id,
    label: line.product ? `${line.product.code}${LABEL_SEPARATOR}${line.product.name}` : `#${line.id}`,
  }
}
