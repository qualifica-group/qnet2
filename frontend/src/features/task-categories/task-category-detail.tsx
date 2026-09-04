import { useTranslation } from 'react-i18next'
import { Tags, History } from 'lucide-react'
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
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { TaskCategoryDetailWithPermissions } from '@/features/task-categories/types'

interface TaskCategoryDetailViewProps {
  taskCategory: TaskCategoryDetailWithPermissions
}

/**
 * Read-only detail of a single task category. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look. `color` is
 * shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskCategoryDetailView({ taskCategory }: TaskCategoryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskCategory.created_at)
  const updatedAt = formatDateTime(taskCategory.updated_at)
  const swatch = swatchClassFor(taskCategory.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={taskCategory.name} icon={<Tags />} />}
        title={taskCategory.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('taskCategories.detail.description')} full>
            {taskCategory.description ? taskCategory.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('taskCategories.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${taskCategory.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('taskCategories.detail.icon')}>
            {taskCategory.icon ? (
              <span className="flex items-center gap-2">
                <DynamicIcon name={taskCategory.icon} className="size-3.5 text-muted-foreground" />
                {taskCategory.icon}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('taskCategories.detail.sort_order')}>{taskCategory.sort_order}</DetailField>
          <DetailField label={t('taskCategories.detail.isActive')}>
            {taskCategory.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {taskCategory.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="task-categories" id={taskCategory.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('taskCategories.detail.created_at')}>{createdAt}</DetailMeta> : null}
      {updatedAt ? <DetailMeta label={t('taskCategories.detail.updated_at')}>{updatedAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
