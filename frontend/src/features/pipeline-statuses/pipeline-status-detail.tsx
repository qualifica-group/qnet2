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
import type { PipelineStatusDetailWithPermissions } from '@/features/pipeline-statuses/types'

interface PipelineStatusDetailViewProps {
  pipelineStatus: PipelineStatusDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single project status, rendered as an
 * enterprise-CRM record (Opportunita' reference layout): the
 * identity/fields card on the left, the activity card on the right, a
 * metadata footer.
 */
export function PipelineStatusDetailView({ pipelineStatus, onEdit }: PipelineStatusDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = pipelineStatus.permissions.resource.update
  const createdAt = formatDateTime(pipelineStatus.created_at)
  const swatch = swatchClassFor(pipelineStatus.color)
  const canViewActivity = pipelineStatus.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('pipeline-statuses', pipelineStatus.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={pipelineStatus.name}
                icon={<Flag />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={pipelineStatus.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('pipelineStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('pipelineStatuses.detail.color')}>
                  {pipelineStatus.color ? (
                    <span className="flex items-center gap-2">
                      <span
                        className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                        aria-hidden="true"
                      />
                      {t(`customFields.colors.${pipelineStatus.color}`)}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('pipelineStatuses.detail.sort_order')}>
                  {pipelineStatus.sort_order}
                </RecordField>
                <RecordField label={t('pipelineStatuses.detail.group')}>
                  {t(`pipelineStatuses.form.group.${pipelineStatus.group}`)}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('pipelineStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
