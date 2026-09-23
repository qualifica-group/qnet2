import { useTranslation } from 'react-i18next'
import { Star } from 'lucide-react'
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
import type { TaskImportanceDetailWithPermissions } from '@/features/task-importances/types'

interface TaskImportanceDetailViewProps {
  taskImportance: TaskImportanceDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single task importance, rendered as an enterprise-CRM
 * record (Opportunita' reference kit): the identity/fields card on the left,
 * the Attivita' tab on the right when authorized, a metadata footer. `color`
 * is shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskImportanceDetailView({ taskImportance, onEdit }: TaskImportanceDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = taskImportance.permissions.resource.update
  const createdAt = formatDateTime(taskImportance.created_at)
  const updatedAt = formatDateTime(taskImportance.updated_at)
  const swatch = swatchClassFor(taskImportance.color)

  const tabs: RecordCollaborationTab[] = taskImportance.permissions.actions.view_activity
    ? [activityLogTab('task-importances', taskImportance.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={taskImportance.name} icon={<Star />} />}
            title={taskImportance.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('taskImportances.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('taskImportances.detail.description')}>
                  {taskImportance.description ? taskImportance.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('taskImportances.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${taskImportance.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('taskImportances.detail.icon')}>
                  {taskImportance.icon ? (
                    <span className="flex items-center gap-2">
                      <DynamicIcon name={taskImportance.icon} className="size-3.5 text-muted-foreground" />
                      {taskImportance.icon}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('taskImportances.detail.sort_order')}>
                  {taskImportance.sort_order}
                </RecordField>
                <RecordField label={t('taskImportances.detail.isActive')}>
                  {taskImportance.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskImportances.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskImportances.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
