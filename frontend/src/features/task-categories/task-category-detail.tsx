import { useTranslation } from 'react-i18next'
import { Tags } from 'lucide-react'
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
import type { TaskCategoryDetailWithPermissions } from '@/features/task-categories/types'

interface TaskCategoryDetailViewProps {
  taskCategory: TaskCategoryDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single task category, rendered as an enterprise-CRM
 * record (Opportunita' reference kit): the identity/fields card on the left,
 * the Attivita' tab on the right when authorized, a metadata footer. `color`
 * is shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskCategoryDetailView({ taskCategory, onEdit }: TaskCategoryDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = taskCategory.permissions.resource.update
  const createdAt = formatDateTime(taskCategory.created_at)
  const updatedAt = formatDateTime(taskCategory.updated_at)
  const swatch = swatchClassFor(taskCategory.color)

  const tabs: RecordCollaborationTab[] = taskCategory.permissions.actions.view_activity
    ? [activityLogTab('task-categories', taskCategory.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={taskCategory.name} icon={<Tags />} />}
            title={taskCategory.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('taskCategories.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('taskCategories.detail.description')}>
                  {taskCategory.description ? taskCategory.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('taskCategories.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${taskCategory.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('taskCategories.detail.icon')}>
                  {taskCategory.icon ? (
                    <span className="flex items-center gap-2">
                      <DynamicIcon name={taskCategory.icon} className="size-3.5 text-muted-foreground" />
                      {taskCategory.icon}
                    </span>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('taskCategories.detail.sort_order')}>
                  {taskCategory.sort_order}
                </RecordField>
                <RecordField label={t('taskCategories.detail.isActive')}>
                  {taskCategory.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
                <RecordField label={t('taskCategories.detail.parent')}>
                  {taskCategory.parent ? taskCategory.parent.name : <DetailEmpty />}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('taskCategories.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('taskCategories.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
