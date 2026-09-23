import { useTranslation } from 'react-i18next'
import { ListChecks } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
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
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { WorkflowStatusBadge } from '@/features/quote-workflows/workflow-status-badge'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'
import type { TaskTemplateDetailWithPermissions, TaskTemplateItem } from '@/features/task-templates/types'

/** One "Fase" group of the read-only detail (spec 0146 D-2), `stage: null` for "Senza fase" — mirrors the editor's own grouping, but off `task_template_stage_id` directly, no client key involved. */
interface TaskTemplateStageItemsGroup {
  stage: { id: number; name: string } | null
  items: TaskTemplateItem[]
}

/** Groups the (already `sort_order`-ordered) items by their stage, in stage order, "Senza fase" last. */
function groupItemsByStage(taskTemplate: TaskTemplateDetailWithPermissions): TaskTemplateStageItemsGroup[] {
  const groups: TaskTemplateStageItemsGroup[] = taskTemplate.stages.map((stage) => ({ stage, items: [] }))
  const unassigned: TaskTemplateStageItemsGroup = { stage: null, items: [] }
  const byStageId = new Map(groups.map((group) => [group.stage?.id, group]))

  for (const item of taskTemplate.items) {
    const group = (item.task_template_stage_id !== null ? byStageId.get(item.task_template_stage_id) : null) ?? unassigned
    group.items.push(item)
  }

  return [...groups, unassigned].filter((group) => group.items.length > 0 || group.stage !== null)
}

interface TaskTemplateDetailViewProps {
  taskTemplate: TaskTemplateDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single task template, including its ordered rows
 * (spec 0124), rendered as an enterprise-CRM record on the same kit
 * Opportunita' uses: the identity/fields card on the left, the activity card
 * on the right, a metadata footer. Each row's status reuses
 * `WorkflowStatusBadge` (domain-type-free by design) rather than a new
 * task-templates-specific pill; each row's attachments mount their own
 * read-only `<DocumentsSection>` scoped to that item's id.
 */
export function TaskTemplateDetailView({ taskTemplate, onEdit }: TaskTemplateDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(taskTemplate.created_at)
  const updatedAt = formatDateTime(taskTemplate.updated_at)
  const canEdit = taskTemplate.permissions.resource.update
  const canViewActivity = taskTemplate.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('task-templates', taskTemplate.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={taskTemplate.name} icon={<ListChecks />} />}
            title={taskTemplate.name}
            subtitle={t('taskTemplates.detail.itemsCount', { count: taskTemplate.items_count })}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('taskTemplates.form.sections.identity.title')}>
              <RecordFieldList>
                <RecordField label={t('taskTemplates.detail.description')}>
                  {taskTemplate.description ? (
                    <RichTextContent html={taskTemplate.description} />
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
                <RecordField label={t('taskTemplates.detail.isActive')}>
                  {taskTemplate.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            <RecordSection title={t('taskTemplates.detail.items.title')} icon={<ListChecks />} full>
              {taskTemplate.items.length === 0 ? (
                <DetailEmpty />
              ) : (
                <div className="flex flex-col gap-4">
                  {groupItemsByStage(taskTemplate).map((group) => (
                    <div key={group.stage?.id ?? 'no-stage'} className="flex flex-col gap-2">
                      <p className="text-xs font-medium text-muted-foreground">
                        {group.stage?.name ?? t('taskTemplates.form.stages.noStage')}
                      </p>
                      <ul className="flex flex-col gap-3">
                        {group.items.map((item) => (
                          <li key={item.id} className="rounded-lg border bg-card p-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                              <p className="text-sm font-medium text-foreground">{item.title}</p>
                              {item.task_status ? (
                                <WorkflowStatusBadge name={item.task_status.name} color={item.task_status.color} />
                              ) : null}
                            </div>
                            {item.description ? (
                              <RichTextContent html={item.description} className="mt-1 text-xs text-muted-foreground" />
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
                    </div>
                  ))}
                </div>
              )}
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('taskTemplates.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('taskTemplates.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
