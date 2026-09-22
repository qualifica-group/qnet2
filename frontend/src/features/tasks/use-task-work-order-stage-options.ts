import { useQuery } from '@tanstack/react-query'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import { taskBoardKeys } from '@/features/work-orders/task-board/query-keys'
import type { TaskWorkOrderStageRef } from '@/features/tasks/types'

export interface TaskWorkOrderStageOptions {
  options: TaskWorkOrderStageRef[]
  isLoading: boolean
  isError: boolean
}

/**
 * The "Fase" select's option list (spec 0146 D-3/AC-030): OPEN fasi of the
 * picked commessa, PLUS the task's own persisted fase even when it has since
 * closed — dropping it silently would strand an edit form on a value it can
 * no longer submit unchanged. Reads the SAME `taskBoardKeys.stages` query the
 * Task board's own picker reads (`fetchWorkOrderStages`, owned by
 * `features/work-orders/task-board/`), so both surfaces share one cache.
 */
export function useTaskWorkOrderStageOptions(
  workOrderId: number | null,
  currentStage: TaskWorkOrderStageRef | null,
): TaskWorkOrderStageOptions {
  const query = useQuery({
    queryKey: taskBoardKeys.stages(workOrderId ?? 0),
    queryFn: () => fetchWorkOrderStages(workOrderId as number),
    enabled: workOrderId !== null,
  })

  const openStages = (query.data ?? []).filter((stage) => stage.closed_at === null)
  const options: TaskWorkOrderStageRef[] = openStages.map((stage) => ({ id: stage.id, name: stage.name }))
  if (currentStage && !options.some((option) => option.id === currentStage.id)) {
    options.push(currentStage)
  }

  return { options, isLoading: query.isLoading, isError: query.isError }
}
