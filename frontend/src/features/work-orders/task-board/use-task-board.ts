import { useQuery } from '@tanstack/react-query'
import { fetchTaskBoard } from '@/features/work-orders/task-board/api'
import { taskBoardKeys } from '@/features/work-orders/task-board/query-keys'
import type { TaskBoardPayload } from '@/features/work-orders/task-board/types'

/** Loads the board's single payload (D-9, AC-011/AC-012): stages, every visible task and `is_read_only`. */
export function useTaskBoard(workOrderId: number) {
  return useQuery<TaskBoardPayload>({
    queryKey: taskBoardKeys.board(workOrderId),
    queryFn: () => fetchTaskBoard(workOrderId),
  })
}
