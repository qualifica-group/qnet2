import { useTranslation } from 'react-i18next'
import { Star, History } from 'lucide-react'
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
import type { TaskImportanceDetailWithPermissions } from '@/features/task-importances/types'

interface TaskImportanceDetailViewProps {
  taskImportance: TaskImportanceDetailWithPermissions
}

/**
 * Read-only detail of a single task importance. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look. `color` is
 * shown as its swatch + localized token name and `icon` as the resolved
 * glyph + its canonical name, both from the shared palette/catalogue.
 */
export function TaskImportanceDetailView({ taskImportance }: TaskImportanceDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskImportance.created_at)
  const updatedAt = formatDateTime(taskImportance.updated_at)
  const swatch = swatchClassFor(taskImportance.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={taskImportance.name} icon={<Star />} />}
        title={taskImportance.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('taskImportances.detail.description')} full>
            {taskImportance.description ? taskImportance.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('taskImportances.detail.color')}>
            <span className="flex items-center gap-2">
              <span
                className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                aria-hidden="true"
              />
              {t(`customFields.colors.${taskImportance.color}`)}
            </span>
          </DetailField>
          <DetailField label={t('taskImportances.detail.icon')}>
            {taskImportance.icon ? (
              <span className="flex items-center gap-2">
                <DynamicIcon name={taskImportance.icon} className="size-3.5 text-muted-foreground" />
                {taskImportance.icon}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('taskImportances.detail.sort_order')}>{taskImportance.sort_order}</DetailField>
          <DetailField label={t('taskImportances.detail.isActive')}>
            {taskImportance.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {taskImportance.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="task-importances" id={taskImportance.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('taskImportances.detail.created_at')}>{createdAt}</DetailMeta> : null}
      {updatedAt ? <DetailMeta label={t('taskImportances.detail.updated_at')}>{updatedAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
