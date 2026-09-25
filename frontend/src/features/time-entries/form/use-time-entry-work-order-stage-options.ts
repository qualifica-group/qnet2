/**
 * The "Fase" select's option list (spec 0163 D-1/AC-008): OPEN fasi of the
 * picked commessa, PLUS the entry's own persisted fase even when it has since
 * closed — dropping it silently would strand an edit form on a value it can
 * no longer submit unchanged (D-2). Reads the SAME `taskBoardKeys.stages`
 * query the Task form's own picker reads (`fetchWorkOrderStages`, owned by
 * `features/work-orders/task-board/`), so both surfaces share one cache; no
 * new endpoint (spec 0146's `GET /work-orders/{id}/stages`).
 */

import { useQuery } from '@tanstack/react-query'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import { taskBoardKeys } from '@/features/work-orders/task-board/query-keys'
import type { TimeEntryWorkOrderStageRef } from '@/features/time-entries/types'

export interface TimeEntryWorkOrderStageOptions {
  options: TimeEntryWorkOrderStageRef[]
  isLoading: boolean
  isError: boolean
}

export function useTimeEntryWorkOrderStageOptions(
  workOrderId: number | null,
  currentStage: TimeEntryWorkOrderStageRef | null,
): TimeEntryWorkOrderStageOptions {
  const query = useQuery({
    queryKey: taskBoardKeys.stages(workOrderId ?? 0),
    queryFn: () => fetchWorkOrderStages(workOrderId as number),
    enabled: workOrderId !== null,
  })

  const openStages = (query.data ?? []).filter((stage) => stage.closed_at === null)
  const options: TimeEntryWorkOrderStageRef[] = openStages.map((stage) => ({ id: stage.id, name: stage.name }))
  if (currentStage && !options.some((option) => option.id === currentStage.id)) {
    options.push(currentStage)
  }

  return { options, isLoading: query.isLoading, isError: query.isError }
}
