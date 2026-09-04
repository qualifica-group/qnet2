import { Contact, Handshake, Hammer } from 'lucide-react'
import { BooleanBadgeCell, DateCell, RelationCell, StatusBadgeCell } from '@/features/table/rich-cells'
import { UserCell, UserStackCell } from '@/features/table/user-cell'
import { TaskPercentageCell } from '@/features/tasks/task-percentage-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0101
 * `data_contract`).
 *
 * The five configured lookups (`task_status`, `task_type`, `task_priority`,
 * `task_importance`, `task_category`) are relation columns projecting
 * `{id, name, color, icon}` — exactly the shape `StatusBadgeCell` already
 * renders, token color and leading dot included (AC-072). Reused rather than
 * forked: changing a color or a label from the configurator changes the grid
 * badge with no code change here.
 *
 * `is_blocked` (AC-086) is a plain boolean with no `enumKey`/`badges`, so the
 * generic fallback would render an empty cell: it gets the shared
 * `BooleanBadgeCell`, the same one `work-orders`' `is_force_closed` uses — and
 * it is a column of its OWN, next to but distinct from the status.
 *
 * `completion_percentage` is the derived column (D-6/AC-022). `title` and
 * `estimated_minutes` stay on the AG Grid default cell.
 *
 * The last four entries cover columns `TaskColumnCatalog` declares
 * `visible: false`: they exist so their FILTERS do (in this engine a filter
 * hangs off a column) and are toggleable from the column picker, so they still
 * need a renderer. `has_subtasks`/`is_subtask` are the derived hierarchy
 * booleans (D-12).
 *
 * There is deliberately NO `created_at`/`updated_at` entry: unlike most
 * modules, `TasksTableDefinition` declares no timestamp column, so such an
 * entry would be dead code. (`tasks.detail.created_at`/`updated_at` DO exist —
 * the detail shows them, the grid does not.)
 */
export const taskColumnRenderers: TableRendererMap = {
  task_status: (params) => <StatusBadgeCell {...params} />,
  task_type: (params) => <StatusBadgeCell {...params} />,
  task_priority: (params) => <StatusBadgeCell {...params} />,
  task_importance: (params) => <StatusBadgeCell {...params} />,
  task_category: (params) => <StatusBadgeCell {...params} />,
  completion_percentage: (params) => <TaskPercentageCell {...params} />,
  is_blocked: (params) => <BooleanBadgeCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  end_date: (params) => <DateCell {...params} />,
  registry: (params) => <RelationCell {...params} icon={Contact} />,
  opportunity: (params) => <RelationCell {...params} icon={Handshake} />,
  work_order: (params) => <RelationCell {...params} icon={Hammer} />,
  requester: (params) => <UserCell {...params} />,
  creator: (params) => <UserCell {...params} />,
  assignees: (params) => <UserStackCell {...params} />,
  watchers: (params) => <UserStackCell {...params} />,
  completion_date: (params) => <DateCell {...params} />,
  has_subtasks: (params) => <BooleanBadgeCell {...params} />,
  is_subtask: (params) => <BooleanBadgeCell {...params} />,
}
