import { useTranslation } from 'react-i18next'
import { Shapes, History } from 'lucide-react'
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
import type { TaskTypeDetailWithPermissions } from '@/features/task-types/types'

interface TaskTypeDetailViewProps {
  taskType: TaskTypeDetailWithPermissions
}

/**
 * Read-only detail of a single task type. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look. `color` is
 * shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskTypeDetailView({ taskType }: TaskTypeDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskType.created_at)
  const updatedAt = formatDateTime(taskType.updated_at)
  const swatch = swatchClassFor(taskType.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={taskType.name} icon={<Shapes />} />}
        title={taskType.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('taskTypes.detail.description')} full>
            {taskType.description ? taskType.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('taskTypes.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${taskType.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('taskTypes.detail.icon')}>
            {taskType.icon ? (
              <span className="flex items-center gap-2">
                <DynamicIcon name={taskType.icon} className="size-3.5 text-muted-foreground" />
                {taskType.icon}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('taskTypes.detail.sort_order')}>{taskType.sort_order}</DetailField>
          <DetailField label={t('taskTypes.detail.isActive')}>
            {taskType.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {taskType.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="task-types" id={taskType.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('taskTypes.detail.created_at')}>{createdAt}</DetailMeta> : null}
      {updatedAt ? <DetailMeta label={t('taskTypes.detail.updated_at')}>{updatedAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
