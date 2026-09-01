import { useTranslation } from 'react-i18next'
import { History, Ruler } from 'lucide-react'
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
import type { UnitOfMeasureDetailWithPermissions } from '@/features/units-of-measure/types'

interface UnitOfMeasureDetailViewProps {
  unitOfMeasure: UnitOfMeasureDetailWithPermissions
}

/**
 * Read-only detail of a single unit of measure. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `PaymentMethodDetailView`). `code` is shown as the hero subtitle,
 * not a labeled field.
 */
export function UnitOfMeasureDetailView({ unitOfMeasure }: UnitOfMeasureDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(unitOfMeasure.created_at)
  const updatedAt = formatDateTime(unitOfMeasure.updated_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={unitOfMeasure.name} icon={<Ruler />} />}
        title={unitOfMeasure.name}
        subtitle={unitOfMeasure.code}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('unitsOfMeasure.detail.symbol')}>{unitOfMeasure.symbol}</DetailField>
          <DetailField label={t('unitsOfMeasure.detail.description')}>
            {unitOfMeasure.description ? unitOfMeasure.description : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {unitOfMeasure.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="units-of-measure" id={unitOfMeasure.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('unitsOfMeasure.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
      {updatedAt ? (
        <DetailMeta label={t('unitsOfMeasure.detail.updated_at')}>{updatedAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
