import { useTranslation } from 'react-i18next'
import { Flag, History } from 'lucide-react'
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
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { ContractStatusDetailWithPermissions } from '@/features/contract-statuses/types'

interface ContractStatusDetailViewProps {
  contractStatus: ContractStatusDetailWithPermissions
}

/**
 * Read-only detail of a single contract status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look.
 */
export function ContractStatusDetailView({ contractStatus }: ContractStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(contractStatus.created_at)
  const swatch = swatchClassFor(contractStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={contractStatus.name} icon={<Flag />} />}
        title={contractStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('contractStatuses.detail.description')} full>
            {contractStatus.description ? contractStatus.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('contractStatuses.detail.color')}>
            {contractStatus.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${contractStatus.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('contractStatuses.detail.sort_order')}>
            {contractStatus.sort_order}
          </DetailField>
          <DetailField label={t('contractStatuses.detail.group')}>
            {t(`contractStatuses.form.group.${contractStatus.group}`)}
          </DetailField>
          <DetailField label={t('contractStatuses.detail.isActive')}>
            {contractStatus.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
          <DetailField label={t('contractStatuses.detail.isDefault')}>
            {contractStatus.is_default ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {contractStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="contract-statuses" id={contractStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('contractStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
