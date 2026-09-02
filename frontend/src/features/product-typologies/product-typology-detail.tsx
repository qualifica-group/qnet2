import { useTranslation } from 'react-i18next'
import { History, Shapes } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'

interface ProductTypologyDetailViewProps {
  productTypology: ProductTypologyDetailWithPermissions
}

/**
 * Read-only detail of a single product typology. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `PaymentMethodDetailView`). `code` is shown as the hero subtitle,
 * not a labeled field.
 */
export function ProductTypologyDetailView({ productTypology }: ProductTypologyDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(productTypology.created_at)
  const updatedAt = formatDateTime(productTypology.updated_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={productTypology.name} icon={<Shapes />} />}
        title={productTypology.name}
        subtitle={productTypology.code}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('productTypologies.detail.description')}>
            {productTypology.description ? productTypology.description : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {productTypology.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="product-typologies" id={productTypology.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('productTypologies.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
      {updatedAt ? (
        <DetailMeta label={t('productTypologies.detail.updated_at')}>{updatedAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
