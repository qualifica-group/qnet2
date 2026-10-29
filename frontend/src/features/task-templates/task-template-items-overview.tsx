import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { WorkflowStatusBadge } from '@/features/workflow-statuses/workflow-status-badge'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'
import { cn } from '@/lib/utils'
import type { TaskTemplateDetail, TaskTemplateItem } from '@/features/task-templates/types'

/** One item of the read-only detail's stage group, with its nesting `depth` (spec 0172 D-1: 0 = root). */
interface TaskTemplateDetailEntry {
  item: TaskTemplateItem
  depth: number
}

/** One "Fase" group of the read-only detail (spec 0146 D-2), `stage: null` for "Senza fase" — mirrors the editor's own grouping, but off `task_template_stage_id`/`parent_id` directly, no client key involved. */
interface TaskTemplateStageItemsGroup {
  stage: { id: number; name: string } | null
  entries: TaskTemplateDetailEntry[]
}

/**
 * Groups the (already `sort_order`-ordered, parent-before-child) items by
 * their EFFECTIVE stage: a root item groups by its own
 * `task_template_stage_id`, a sub-item (whose OWN stage is always `null`,
 * spec 0172 D-3) follows the group of the root it currently sits under —
 * mirrors the editor's `groupItemRowsByStage`.
 */
function groupItemsByStage(taskTemplate: TaskTemplateDetail): TaskTemplateStageItemsGroup[] {
  const groups: TaskTemplateStageItemsGroup[] = taskTemplate.stages.map((stage) => ({ stage, entries: [] }))
  const unassigned: TaskTemplateStageItemsGroup = { stage: null, entries: [] }
  const byStageId = new Map(groups.map((group) => [group.stage?.id, group]))

  let currentGroup = unassigned
  const depthById = new Map<number, number>()

  for (const item of taskTemplate.items) {
    let depth: number
    if (item.parent_id === null) {
      currentGroup =
        (item.task_template_stage_id !== null ? byStageId.get(item.task_template_stage_id) : null) ?? unassigned
      depth = 0
    } else {
      depth = (depthById.get(item.parent_id) ?? 0) + 1
    }
    depthById.set(item.id, depth)
    currentGroup.entries.push({ item, depth })
  }

  return [...groups, unassigned].filter((group) => group.entries.length > 0 || group.stage !== null)
}

/** One Tailwind class per nesting `depth` (1..`MAX_ITEM_DEPTH`) — a literal lookup so Tailwind's static scan keeps finding them (mirrors `TaskTemplateSubItemRow`'s own). */
const ITEM_INDENT_CLASS: Record<number, string> = { 1: 'ml-4', 2: 'ml-8', 3: 'ml-12' }

/**
 * The saved "Fasi e attivita'" board, read-only (spec 0146 D-2, spec 0172):
 * one group per stage, "Senza fase" last, sub-items indented under their
 * root, each row's attachments in a read-only `<DocumentsSection>`. The
 * board row's closed value on the detail (spec 0227 D-3).
 */
export function TaskTemplateItemsOverview({ taskTemplate }: { taskTemplate: TaskTemplateDetail }) {
  const { t } = useTranslation()

  if (taskTemplate.items.length === 0) {
    return <DetailEmpty />
  }

  return (
    <div className="flex flex-col gap-4">
      {groupItemsByStage(taskTemplate).map((group) => (
        <div key={group.stage?.id ?? 'no-stage'} className="flex flex-col gap-2">
          <p className="text-xs font-medium text-muted-foreground">
            {group.stage?.name ?? t('taskTemplates.form.stages.noStage')}
          </p>
          <ul className="flex flex-col gap-3">
            {group.entries.map(({ item, depth }) => (
              <li
                key={item.id}
                className={cn('rounded-lg border bg-card p-3', ITEM_INDENT_CLASS[depth])}
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-sm font-medium text-foreground">{item.title}</p>
                  {item.task_status ? (
                    <WorkflowStatusBadge name={item.task_status.name} color={item.task_status.color} />
                  ) : null}
                </div>
                {item.work_order_workflow_status ? (
                  <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span>{t('taskTemplates.detail.items.workOrderStatus')}</span>
                    <WorkflowStatusBadge
                      name={item.work_order_workflow_status.name}
                      color={item.work_order_workflow_status.color}
                    />
                    {item.work_order_workflow_status.progress_percentage !== null ? (
                      <span>{`${item.work_order_workflow_status.progress_percentage}%`}</span>
                    ) : null}
                  </div>
                ) : null}
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
  )
}
