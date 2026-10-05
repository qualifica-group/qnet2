import { RelationCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { FinancialAccountTypeCell } from '@/features/financial-accounts/financial-account-type-cell'

/**
 * Custom cell renderers keyed by the backend column `id`. `name`, `iban` and
 * `notes` use the default cells; `type` shows the translated label and the
 * hidden-by-default `company` column the shared relation cell.
 */
export const financialAccountColumnRenderers: TableRendererMap = {
  type: (params) => <FinancialAccountTypeCell {...params} />,
  company: (params) => <RelationCell {...params} />,
}
