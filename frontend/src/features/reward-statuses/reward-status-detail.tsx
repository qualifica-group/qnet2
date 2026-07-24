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
import type { RewardStatusDetailWithPermissions } from '@/features/reward-statuses/types'

interface RewardStatusDetailViewProps {
  rewardStatus: RewardStatusDetailWithPermissions
}

/**
 * Read-only detail of a single reward status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `OpportunityStatusDetailView`).
 */
export function RewardStatusDetailView({ rewardStatus }: RewardStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(rewardStatus.created_at)
  const updatedAt = formatDateTime(rewardStatus.updated_at)
  const swatch = swatchClassFor(rewardStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={rewardStatus.name} icon={<Flag />} />}
        title={rewardStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('rewardStatuses.detail.description')}>
            {rewardStatus.description ? rewardStatus.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('rewardStatuses.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${rewardStatus.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('rewardStatuses.detail.sort_order')}>
            {rewardStatus.sort_order}
          </DetailField>
          <DetailField label={t('rewardStatuses.detail.is_active')}>
            {rewardStatus.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {rewardStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="reward-statuses" id={rewardStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('rewardStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
      {updatedAt ? (
        <DetailMeta label={t('rewardStatuses.detail.updated_at')}>{updatedAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
