import { useTranslation } from 'react-i18next'
import { Gift, History } from 'lucide-react'
import {
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'

interface RewardTypeDetailViewProps {
  rewardType: RewardTypeDetailWithPermissions
}

/**
 * Read-only detail of a single reward type. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `OpportunityStatusDetailView`). Unlike its template, `color` is
 * always set (D-5) and both `created_at`/`updated_at` are shown (spec 0058
 * data_contract), so both dates sit in the grid rather than a single footer
 * `DetailMeta`.
 */
export function RewardTypeDetailView({ rewardType }: RewardTypeDetailViewProps) {
  const { t } = useTranslation()
  const swatch = swatchClassFor(rewardType.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={rewardType.name} icon={<Gift />} />}
        title={rewardType.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('rewardTypes.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${rewardType.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('rewardTypes.detail.created_at')}>
            {formatDateTime(rewardType.created_at)}
          </DetailField>
          <DetailField label={t('rewardTypes.detail.updated_at')}>
            {formatDateTime(rewardType.updated_at)}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {rewardType.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="reward-types" id={rewardType.id} />
        </DetailSection>
      ) : null}
    </DetailPanel>
  )
}
