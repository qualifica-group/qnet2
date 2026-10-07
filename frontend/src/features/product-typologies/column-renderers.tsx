/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { useTranslation } from 'react-i18next'
import { EmptyCell } from '@/features/table/cell-renderers'
import { SupplierCommissionDirectionCell } from '@/features/product-typologies/supplier-commission-direction-cell'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell, CodeBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** The `color` column: a badge in the token's colour, named after it, as the typology badge shows it elsewhere. */
function ColorBadgeCell({ token }: { token: unknown }) {
  const { t } = useTranslation()
  if (typeof token !== 'string' || token === '') {
    return <EmptyCell align="left" />
  }
  return (
    <div className="flex h-full items-center px-2">
      <ProductTypologyBadge name={t(`customFields.colors.${token}`)} color={token} />
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library. `name`/`description` fall back
 * to the AG Grid default cells; `code` renders as a compact monospace badge
 * (mirrors `paymentMethodColumnRenderers`); `created_at`/`updated_at` reuse
 * the shared datetime renderer.
 */
export const productTypologyColumnRenderers: TableRendererMap = {
  color: ({ value }) => <ColorBadgeCell token={value} />,
  code: (params) => <CodeBadgeCell {...params} />,
  supplier_commission_enabled: (params) => <BooleanBadgeCell {...params} />,
  supplier_commission_direction: ({ value }) => <SupplierCommissionDirectionCell value={value} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
