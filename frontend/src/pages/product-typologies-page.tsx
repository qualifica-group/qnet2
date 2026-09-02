import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProductTypologiesTable } from '@/features/product-typologies/product-typologies-table'

/**
 * Product typologies page. Light composition only: gates access with
 * `product-typologies.viewAny` and mounts the thin product-typologies adapter,
 * which in turn mounts the generic table (`domain="product-typologies"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here (mirrors `UnitsOfMeasurePage`).
 */
export default function ProductTypologiesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="product-typologies.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('productTypologies.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProductTypologiesTable />
      </div>
    </Can>
  )
}
