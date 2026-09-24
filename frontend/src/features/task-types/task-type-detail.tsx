import { useTranslation } from 'react-i18next'
import { Shapes } from 'lucide-react'
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
import type { TaskTypeDetailWithPermissions } from '@/features/task-types/types'

interface TaskTypeDetailViewProps {
  taskType: TaskTypeDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single task type, rendered as an enterprise-CRM
 * record (Opportunita' reference kit): the identity/fields card on the left,
 * the Attivita' tab on the right when authorized, a metadata footer. `color`
 * is shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskTypeDetailView({ taskType, onEdit }: TaskTypeDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = taskType.permissions.resource.update
  const createdAt = formatDateTime(taskType.created_at)
  const updatedAt = formatDateTime(taskType.updated_at)
  const swatch = swatchClassFor(taskType.color)

  const tabs: RecordCollaborationTab[] = taskType.permissions.actions.view_activity
    ? [activityLogTab('task-types', taskType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={taskType.name} icon={<Shapes />} />}
            title={taskType.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('taskTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('taskTypes.detail.description')}>
                  {taskType.description ? taskType.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('taskTypes.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${taskType.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('taskTypes.detail.icon')}>
                  {taskType.icon ? (
                    <span className="flex items-center gap-2">
                      <DynamicIcon name={taskType.icon} className="size-3.5 text-muted-foreground" />
                      {taskType.icon}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('taskTypes.detail.sort_order')}>{taskType.sort_order}</RecordField>
                <RecordField label={t('taskTypes.detail.isActive')}>
                  {taskType.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
                <RecordField label={t('taskTypes.detail.isDefault')}>
                  {taskType.is_default ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskTypes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskTypes.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
