import { useTranslation } from 'react-i18next'
import { SignalHigh, History } from 'lucide-react'
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
import type { TaskPriorityDetailWithPermissions } from '@/features/task-priorities/types'

interface TaskPriorityDetailViewProps {
  taskPriority: TaskPriorityDetailWithPermissions
}

/**
 * Read-only detail of a single task priority. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look. `color` is
 * shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskPriorityDetailView({ taskPriority }: TaskPriorityDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskPriority.created_at)
  const updatedAt = formatDateTime(taskPriority.updated_at)
  const swatch = swatchClassFor(taskPriority.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={taskPriority.name} icon={<SignalHigh />} />}
        title={taskPriority.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('taskPriorities.detail.description')} full>
            {taskPriority.description ? taskPriority.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('taskPriorities.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${taskPriority.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('taskPriorities.detail.icon')}>
            {taskPriority.icon ? (
              <span className="flex items-center gap-2">
                <DynamicIcon name={taskPriority.icon} className="size-3.5 text-muted-foreground" />
                {taskPriority.icon}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('taskPriorities.detail.sort_order')}>{taskPriority.sort_order}</DetailField>
          <DetailField label={t('taskPriorities.detail.isActive')}>
            {taskPriority.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {taskPriority.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="task-priorities" id={taskPriority.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('taskPriorities.detail.created_at')}>{createdAt}</DetailMeta> : null}
      {updatedAt ? <DetailMeta label={t('taskPriorities.detail.updated_at')}>{updatedAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
