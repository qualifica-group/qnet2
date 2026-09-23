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
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { TaskStatusDetailWithPermissions } from '@/features/task-statuses/types'

interface TaskStatusDetailViewProps {
  taskStatus: TaskStatusDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single task status, rendered as an enterprise-CRM
 * record (Opportunita' reference layout): the identity/fields card on the
 * left, the activity card on the right, a metadata footer. `color` is shown
 * as its swatch + localized token name, `icon` as the resolved glyph + its
 * canonical name (both from the shared palette/catalogue) and `group` as the
 * localized label of its fixed phase.
 */
export function TaskStatusDetailView({ taskStatus, onEdit }: TaskStatusDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = taskStatus.permissions.resource.update
  const createdAt = formatDateTime(taskStatus.created_at)
  const updatedAt = formatDateTime(taskStatus.updated_at)
  const swatch = swatchClassFor(taskStatus.color)
  const canViewActivity = taskStatus.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('task-statuses', taskStatus.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={
              <DetailMonogram
                name={taskStatus.name}
                icon={<Flag />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={taskStatus.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('taskStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('taskStatuses.detail.description')}>
                  {taskStatus.description ? taskStatus.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('taskStatuses.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${taskStatus.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('taskStatuses.detail.icon')}>
                  {taskStatus.icon ? (
                    <span className="flex items-center gap-2">
                      <DynamicIcon name={taskStatus.icon} className="size-3.5 text-muted-foreground" />
                      {taskStatus.icon}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('taskStatuses.detail.group')}>
                  {t(`taskStatuses.form.group.${taskStatus.group}`)}
                </RecordField>
                <RecordField label={t('taskStatuses.detail.completionPercentage')}>
                  {`${taskStatus.completion_percentage}%`}
                </RecordField>
                <RecordField label={t('taskStatuses.detail.sort_order')}>
                  {taskStatus.sort_order}
                </RecordField>
                <RecordField label={t('taskStatuses.detail.isActive')}>
                  {taskStatus.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskStatuses.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
