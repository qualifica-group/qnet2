import { NodeViewWrapper, type NodeViewProps } from '@tiptap/react'
import { useTranslation } from 'react-i18next'
import { CheckCircle2, Circle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { usePageBlocks } from '@/features/work-order-page/blocks/page-blocks-context'
import { BLOCK_DATA_TYPE } from '@/features/work-order-page/editor/constants'

/**
 * Node view of a cited task. The summary comes from the page payload (the
 * server filters it by task visibility); an unavailable or id-less node only
 * shows the placeholder, never a title (spec 0380 D-6).
 */
export function TaskRefView({ node }: NodeViewProps) {
  const { t } = useTranslation()
  const { tasks, onOpenTask } = usePageBlocks()
  const taskId = typeof node.attrs.taskId === 'number' ? node.attrs.taskId : null
  const task = taskId === null ? undefined : tasks[String(taskId)]

  if (taskId === null || !task || !task.available) {
    return (
      <NodeViewWrapper data-type={BLOCK_DATA_TYPE.taskRef} contentEditable={false} className="my-1">
        <div className="rounded-md border border-dashed bg-card px-2.5 py-1.5 text-sm text-muted-foreground">
          {t('workOrderPage.taskRef.unavailable')}
        </div>
      </NodeViewWrapper>
    )
  }

  const title = task.title ?? ''
  const assignees = task.assignees.map((assignee) => assignee.name).join(', ')
  const Icon = task.completed ? CheckCircle2 : Circle

  return (
    <NodeViewWrapper data-type={BLOCK_DATA_TYPE.taskRef} contentEditable={false} className="my-1">
      <button
        type="button"
        aria-label={t('workOrderPage.taskRef.open', { title })}
        onClick={() => onOpenTask(taskId)}
        className="flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-md border bg-card px-2.5 py-1.5 text-left text-sm shadow-sm hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring"
      >
        <Icon
          className={cn('size-4 shrink-0', task.completed ? 'text-primary' : 'text-muted-foreground')}
          aria-label={task.completed ? t('workOrderPage.taskRef.completed') : t('workOrderPage.taskRef.open_state')}
          role="img"
        />
        <span className={cn('min-w-0 flex-1 truncate font-medium', task.completed && 'text-muted-foreground line-through')}>
          {title}
        </span>
        {task.status ? (
          <Badge variant="secondary" className={cn(BADGE_BASE, badgeColorClass(task.status.color))}>
            {task.status.name}
          </Badge>
        ) : null}
        {assignees ? <span className="max-w-48 truncate text-xs text-muted-foreground">{assignees}</span> : null}
        {task.end_date ? (
          <span className="text-xs text-muted-foreground">
            {t('workOrderPage.taskRef.due', { date: formatDate(task.end_date) })}
          </span>
        ) : null}
      </button>
    </NodeViewWrapper>
  )
}
