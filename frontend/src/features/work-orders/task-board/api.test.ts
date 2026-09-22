import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import {
  bulkBoardTaskAction,
  closeWorkOrderStage,
  createWorkOrderStage,
  deleteWorkOrderStage,
  fetchTaskBoard,
  fetchWorkOrderStages,
  moveBoardTask,
  renameWorkOrderStage,
  reopenWorkOrderStage,
  reorderWorkOrderStages,
} from '@/features/work-orders/task-board/api'
import { taskBoardPayload, workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)
const postMock = vi.mocked(apiClient.post)
const patchMock = vi.mocked(apiClient.patch)
const deleteMock = vi.mocked(apiClient.delete)

function envelope<T>(data: T) {
  return { data: { success: true, message: 'ok', data } }
}

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  patchMock.mockReset()
  deleteMock.mockReset()
})

describe('fetchTaskBoard', () => {
  it('GETs the single board payload for the commessa', async () => {
    const payload = taskBoardPayload()
    getMock.mockResolvedValue(envelope(payload))

    const result = await fetchTaskBoard(9)

    expect(getMock).toHaveBeenCalledWith('/work-orders/9/task-board')
    expect(result).toEqual(payload)
  })
})

describe('fetchWorkOrderStages', () => {
  it('GETs the standalone fase list', async () => {
    const stages = [workOrderStage()]
    getMock.mockResolvedValue(envelope(stages))

    const result = await fetchWorkOrderStages(9)

    expect(getMock).toHaveBeenCalledWith('/work-orders/9/stages')
    expect(result).toEqual(stages)
  })
})

describe('fase CRUD/lifecycle', () => {
  it('POSTs a new fase name', async () => {
    const stage = workOrderStage({ name: 'Collaudo' })
    postMock.mockResolvedValue(envelope(stage))

    const result = await createWorkOrderStage(9, 'Collaudo')

    expect(postMock).toHaveBeenCalledWith('/work-orders/9/stages', { name: 'Collaudo' })
    expect(result).toEqual(stage)
  })

  it('PATCHes a rename', async () => {
    const stage = workOrderStage({ id: 2, name: 'Rinominata' })
    patchMock.mockResolvedValue(envelope(stage))

    const result = await renameWorkOrderStage(9, 2, 'Rinominata')

    expect(patchMock).toHaveBeenCalledWith('/work-orders/9/stages/2', { name: 'Rinominata' })
    expect(result).toEqual(stage)
  })

  it('DELETEs a fase', async () => {
    deleteMock.mockResolvedValue({ data: null })

    await deleteWorkOrderStage(9, 2)

    expect(deleteMock).toHaveBeenCalledWith('/work-orders/9/stages/2')
  })

  it('POSTs the reordered stage_ids', async () => {
    const stages = [workOrderStage({ id: 2, sort_order: 0 }), workOrderStage({ id: 1, sort_order: 1 })]
    postMock.mockResolvedValue(envelope(stages))

    const result = await reorderWorkOrderStages(9, [2, 1])

    expect(postMock).toHaveBeenCalledWith('/work-orders/9/stages/reorder', { stage_ids: [2, 1] })
    expect(result).toEqual(stages)
  })

  it('POSTs close with no body and returns the closed fase', async () => {
    const stage = workOrderStage({ closed_at: '2026-09-22T10:00:00Z', closed_by: { id: 1, name: 'Ada' } })
    postMock.mockResolvedValue(envelope(stage))

    const result = await closeWorkOrderStage(9, 1)

    expect(postMock).toHaveBeenCalledWith('/work-orders/9/stages/1/close')
    expect(result).toEqual(stage)
  })

  it('POSTs reopen with no body and returns the reopened fase', async () => {
    const stage = workOrderStage()
    postMock.mockResolvedValue(envelope(stage))

    const result = await reopenWorkOrderStage(9, 1)

    expect(postMock).toHaveBeenCalledWith('/work-orders/9/stages/1/reopen')
    expect(result).toEqual(stage)
  })
})

describe('moveBoardTask', () => {
  it('POSTs task_id/work_order_stage_id/position and returns the repositioned rows', async () => {
    const moveResult = { tasks: [{ id: 1, work_order_stage_id: 2, stage_position: 0 }] }
    postMock.mockResolvedValue(envelope(moveResult))

    const result = await moveBoardTask(9, { task_id: 1, work_order_stage_id: 2, position: 0 })

    expect(postMock).toHaveBeenCalledWith('/work-orders/9/task-board/move', {
      task_id: 1,
      work_order_stage_id: 2,
      position: 0,
    })
    expect(result).toEqual(moveResult)
  })
})

describe('bulkBoardTaskAction', () => {
  it('POSTs the discriminated payload and returns the per-task results', async () => {
    const bulkResult = {
      results: [{ task_id: 1, ok: true, message: null }],
      succeeded: 1,
      failed: 0,
    }
    postMock.mockResolvedValue(envelope(bulkResult))

    const payload = { action: 'assign' as const, task_ids: [1], assignee_ids: [31] }
    const result = await bulkBoardTaskAction(9, payload)

    expect(postMock).toHaveBeenCalledWith('/work-orders/9/task-board/bulk', payload)
    expect(result).toEqual(bulkResult)
  })
})
