import { useTranslation } from 'react-i18next'
import { List, KanbanSquare, ListTree } from 'lucide-react'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { TaskListViewMode } from '@/features/tasks/use-task-list-view-mode'
import type { TaskKanbanMode } from '@/features/tasks/use-task-kanban-mode'

interface TaskViewModeSelectorProps {
  viewMode: TaskListViewMode
  onViewModeChange: (mode: TaskListViewMode) => void
  kanbanMode: TaskKanbanMode
  onKanbanModeChange: (mode: TaskKanbanMode) => void
}

/**
 * Compact Analitica/Sintetica/Kanban selector (spec 0157 D-1/D-2/D-5), with
 * the Kanban sub-choice (per stato/per scadenza) shown only once Kanban is
 * selected — a second, narrower strip right next to it (ui-design.md §2:
 * small controls, no giant elements).
 */
export function TaskViewModeSelector({
  viewMode,
  onViewModeChange,
  kanbanMode,
  onKanbanModeChange,
}: TaskViewModeSelectorProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Tabs value={viewMode} onValueChange={(value) => onViewModeChange(value as TaskListViewMode)}>
        <TabsList>
          <TabsTrigger value="analytic" className="text-xs">
            <List aria-hidden="true" className="size-3.5" />
            {t('tasks.views.analytic')}
          </TabsTrigger>
          <TabsTrigger value="synthetic" className="text-xs">
            <ListTree aria-hidden="true" className="size-3.5" />
            {t('tasks.views.synthetic')}
          </TabsTrigger>
          <TabsTrigger value="kanban" className="text-xs">
            <KanbanSquare aria-hidden="true" className="size-3.5" />
            {t('tasks.views.kanban')}
          </TabsTrigger>
        </TabsList>
      </Tabs>

      {viewMode === 'kanban' ? (
        <Tabs value={kanbanMode} onValueChange={(value) => onKanbanModeChange(value as TaskKanbanMode)}>
          <TabsList>
            <TabsTrigger value="status" className="text-xs">
              {t('tasks.views.kanbanByStatus')}
            </TabsTrigger>
            <TabsTrigger value="due" className="text-xs">
              {t('tasks.views.kanbanByDue')}
            </TabsTrigger>
          </TabsList>
        </Tabs>
      ) : null}
    </div>
  )
}
