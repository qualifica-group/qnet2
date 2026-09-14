import { useTranslation } from 'react-i18next'
import { History, ListChecks } from 'lucide-react'
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
import { DocumentsSection } from '@/features/attachments/documents-section'
import { WorkflowStatusBadge } from '@/features/quote-workflows/workflow-status-badge'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'
import type { TaskTemplateDetailWithPermissions } from '@/features/task-templates/types'

interface TaskTemplateDetailViewProps {
  taskTemplate: TaskTemplateDetailWithPermissions
}

/**
 * Read-only detail of a single task template, including its ordered rows
 * (spec 0124). Purely presentational: the caller (the table's "view" sheet)
 * fetches the fresh detail and passes it down. Composed from the shared
 * detail kit for a consistent CRM look (mirrors `ProductTypologyDetailView`/
 * `QuoteWorkflowDetailView`). Each row's status reuses
 * `WorkflowStatusBadge` (domain-type-free by design) rather than a new
 * task-templates-specific pill; each row's attachments mount their own
 * read-only `<DocumentsSection>` scoped to that item's id.
 */
export function TaskTemplateDetailView({ taskTemplate }: TaskTemplateDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskTemplate.created_at)
  const updatedAt = formatDateTime(taskTemplate.updated_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={taskTemplate.name} icon={<ListChecks />} />}
        title={taskTemplate.name}
        subtitle={t('taskTemplates.detail.itemsCount', { count: taskTemplate.items_count })}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('taskTemplates.detail.description')} full>
            {taskTemplate.description ? taskTemplate.description : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('taskTemplates.detail.isActive')}>
            {taskTemplate.is_active ? t('common.yes') : t('common.no')}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('taskTemplates.detail.items.title')} icon={<ListChecks />}>
        {taskTemplate.items.length === 0 ? (
          <DetailEmpty />
        ) : (
          <ul className="flex flex-col gap-3">
            {taskTemplate.items.map((item) => (
              <li key={item.id} className="rounded-lg border bg-card p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-sm font-medium text-foreground">{item.title}</p>
                  {item.task_status ? (
                    <WorkflowStatusBadge name={item.task_status.name} color={item.task_status.color} />
                  ) : null}
                </div>
                {item.description ? (
                  <p className="mt-1 text-xs text-muted-foreground">{item.description}</p>
                ) : null}
                <div className="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
                  <span>{t('taskTemplates.detail.items.dueOffsetDays', { count: item.due_offset_days })}</span>
                  {item.estimated_minutes !== null ? (
                    <span>
                      {t('taskTemplates.detail.items.estimatedMinutes', { value: item.estimated_minutes })}
                    </span>
                  ) : null}
                </div>
                <div className="mt-2">
                  <DocumentsSection
                    resource={TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS}
                    id={item.id}
                    canUpload={false}
                    canDelete={false}
                  />
                </div>
              </li>
            ))}
          </ul>
        )}
      </DetailSection>

      {taskTemplate.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="task-templates" id={taskTemplate.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('taskTemplates.detail.created_at')}>{createdAt}</DetailMeta> : null}
      {updatedAt ? <DetailMeta label={t('taskTemplates.detail.updated_at')}>{updatedAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
