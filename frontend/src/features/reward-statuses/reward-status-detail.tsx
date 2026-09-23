import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { RewardStatusDetailWithPermissions } from '@/features/reward-statuses/types'

interface RewardStatusDetailViewProps {
  rewardStatus: RewardStatusDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single reward status, rendered as an enterprise-CRM
 * record (Opportunita' reference layout): the identity/fields card on the
 * left, the activity card on the right, a metadata footer.
 */
export function RewardStatusDetailView({ rewardStatus, onEdit }: RewardStatusDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = rewardStatus.permissions.resource.update
  const createdAt = formatDateTime(rewardStatus.created_at)
  const updatedAt = formatDateTime(rewardStatus.updated_at)
  const swatch = swatchClassFor(rewardStatus.color)
  const canViewActivity = rewardStatus.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('reward-statuses', rewardStatus.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={rewardStatus.name} icon={<Flag />} className="size-10 text-base [&>svg]:size-5" />}
            title={rewardStatus.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('rewardStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('rewardStatuses.detail.description')}>
                  {rewardStatus.description ? rewardStatus.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('rewardStatuses.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${rewardStatus.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('rewardStatuses.detail.sort_order')}>
                  {rewardStatus.sort_order}
                </RecordField>
                <RecordField label={t('rewardStatuses.detail.is_active')}>
                  {rewardStatus.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('rewardStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('rewardStatuses.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
