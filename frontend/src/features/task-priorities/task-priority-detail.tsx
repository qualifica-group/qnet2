import { useTranslation } from 'react-i18next'
import { SignalHigh } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
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
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { formatDateTime } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'
import type { TaskPriorityDetailWithPermissions } from '@/features/task-priorities/types'

interface TaskPriorityDetailViewProps {
  taskPriority: TaskPriorityDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single task priority, rendered as an enterprise-CRM
 * record (Opportunita' reference kit): the identity/fields card on the left,
 * the Attivita' tab on the right when authorized, a metadata footer. `color`
 * is shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskPriorityDetailView({ taskPriority, onEdit }: TaskPriorityDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = taskPriority.permissions.resource.update
  const createdAt = formatDateTime(taskPriority.created_at)
  const updatedAt = formatDateTime(taskPriority.updated_at)
  const swatch = swatchClassFor(taskPriority.color)

  const tabs: RecordCollaborationTab[] = taskPriority.permissions.actions.view_activity
    ? [activityLogTab('task-priorities', taskPriority.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={taskPriority.name} icon={<SignalHigh />} />}
            title={taskPriority.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('taskPriorities.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('taskPriorities.detail.description')}>
                  {taskPriority.description ? taskPriority.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('taskPriorities.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${taskPriority.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('taskPriorities.detail.icon')}>
                  {taskPriority.icon ? (
                    <span className="flex items-center gap-2">
                      <DynamicIcon name={taskPriority.icon} className="size-3.5 text-muted-foreground" />
                      {taskPriority.icon}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('taskPriorities.detail.sort_order')}>
                  {taskPriority.sort_order}
                </RecordField>
                <RecordField label={t('taskPriorities.detail.isActive')}>
                  {taskPriority.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskPriorities.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskPriorities.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
