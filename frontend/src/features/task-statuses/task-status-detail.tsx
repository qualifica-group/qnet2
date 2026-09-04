import { useTranslation } from 'react-i18next'
import { Flag, History } from 'lucide-react'
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
import type { TaskStatusDetailWithPermissions } from '@/features/task-statuses/types'

interface TaskStatusDetailViewProps {
  taskStatus: TaskStatusDetailWithPermissions
}

/**
 * Read-only detail of a single task status. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look. `color` is
 * shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskStatusDetailView({ taskStatus }: TaskStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskStatus.created_at)
  const updatedAt = formatDateTime(taskStatus.updated_at)
  const swatch = swatchClassFor(taskStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={taskStatus.name} icon={<Flag />} />}
        title={taskStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('taskStatuses.detail.description')} full>
            {taskStatus.description ? taskStatus.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('taskStatuses.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${taskStatus.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('taskStatuses.detail.icon')}>
            {taskStatus.icon ? (
              <span className="flex items-center gap-2">
                <DynamicIcon name={taskStatus.icon} className="size-3.5 text-muted-foreground" />
                {taskStatus.icon}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('taskStatuses.detail.completionPercentage')}>
            {`${taskStatus.completion_percentage}%`}
          </DetailField>
          <DetailField label={t('taskStatuses.detail.sort_order')}>{taskStatus.sort_order}</DetailField>
          <DetailField label={t('taskStatuses.detail.isActive')}>
            {taskStatus.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {taskStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="task-statuses" id={taskStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('taskStatuses.detail.created_at')}>{createdAt}</DetailMeta> : null}
      {updatedAt ? <DetailMeta label={t('taskStatuses.detail.updated_at')}>{updatedAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
