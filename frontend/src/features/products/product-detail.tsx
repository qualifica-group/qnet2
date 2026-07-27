import { useTranslation } from 'react-i18next'
import { History, Package } from 'lucide-react'
import {
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { formatDecimal } from '@/features/products/column-renderers'
import { ProductAttributeValuesSection } from '@/features/products/product-attribute-values-section'
import type { ProductDetailWithPermissions } from '@/features/products/types'

interface ProductDetailViewProps {
  product: ProductDetailWithPermissions
}

/**
 * Read-only detail of a single product. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down
 * (mirrors `ReferentTypeDetailView`).
 */
export function ProductDetailView({ product }: ProductDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(product.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={product.name} icon={<Package />} />}
        title={product.name}
        subtitle={product.category?.name}
      />

      <DetailSection title={t('products.detail.details')}>
        <DetailGrid>
          <DetailField label={t('products.columns.product_type')}>
            <Badge variant="secondary">{enumLabelOf('product_type', product.product_type)}</Badge>
          </DetailField>
          {product.description ? (
            <DetailField label={t('products.columns.description')} full>
              {product.description}
            </DetailField>
          ) : null}
          {product.cost !== null && (
            <DetailField label={t('products.columns.cost')}>{formatDecimal(product.cost)}</DetailField>
          )}
          {product.price !== null && (
            <DetailField label={t('products.columns.price')}>{formatDecimal(product.price)}</DetailField>
          )}
          {product.business_function && (
            <DetailField label={t('products.columns.business_function')}>
              {product.business_function.name}
            </DetailField>
          )}
          {product.vat_rate && (
            <DetailField label={t('products.form.vatRate')}>{product.vat_rate.name}</DetailField>
          )}
          {product.supplier && (
            <DetailField label={t('products.form.supplier')}>{product.supplier.name}</DetailField>
          )}
          {product.state && (
            <DetailField label={t('products.form.state')}>{product.state.name}</DetailField>
          )}
        </DetailGrid>
      </DetailSection>

      <ProductAttributeValuesSection
        layout={product.attribute_layout ?? null}
        attributes={product.applicable_attributes ?? []}
        values={product.attribute_values ?? {}}
      />

      {product.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="products" id={product.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('products.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
