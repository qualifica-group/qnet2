import { useTranslation } from 'react-i18next'
import { Gift } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
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
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'

interface RewardTypeDetailViewProps {
  rewardType: RewardTypeDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single reward type, rendered as an enterprise-CRM
 * record (Opportunita' reference layout): the identity/fields card on the
 * left, the activity card on the right. Unlike some of its siblings,
 * `color` is always set (D-5) and both `created_at`/`updated_at` are shown
 * (spec 0058 data_contract), so both dates sit in the footer meta strip.
 */
export function RewardTypeDetailView({ rewardType, onEdit }: RewardTypeDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = rewardType.permissions.resource.update
  const swatch = swatchClassFor(rewardType.color)
  const canViewActivity = rewardType.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('reward-types', rewardType.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={rewardType.name}
                icon={<Gift />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={rewardType.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('rewardTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('rewardTypes.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${rewardType.color}`)}
                  </span>
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        <span>
          <span className="font-medium">{t('rewardTypes.detail.created_at')}</span>{' '}
          <span aria-hidden="true">·</span> {formatDateTime(rewardType.created_at)}
        </span>
        <span>
          <span className="font-medium">{t('rewardTypes.detail.updated_at')}</span>{' '}
          <span aria-hidden="true">·</span> {formatDateTime(rewardType.updated_at)}
        </span>
      </RecordMeta>
    </RecordCanvas>
  )
}
