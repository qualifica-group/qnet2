import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  approveTask,
  blockTask,
  completeTask,
  rejectTask,
  requestTaskUpdate,
  uncompleteTask,
  unblockTask,
} from '@/features/tasks/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)

/** The action flags the actions bar reads; they follow the task's own phase/`is_blocked` (D-8). */
const PERMISSIONS = {
  resource: { view: true, create: false, update: true, delete: false, export: false, import: false },
  fields: {},
  actions: { complete: false, uncomplete: true, approve: false, reject: false, block: false, unblock: true },
}

function envelope() {
  return {
    data: {
      success: true,
      message: 'ok',
      data: { id: 7, task_status_id: 4, is_blocked: false },
      permissions: PERMISSIONS,
    },
  }
}

beforeEach(() => {
  postMock.mockReset()
  postMock.mockResolvedValue(envelope())
})

/**
 * Mirrors the contracts module's own regression (segnalazione utente
 * 2026-08-31): every write MUST return the `permissions` envelope sibling
 * (`okWithPermissions`), never `data` alone — the six action flags depend on
 * the task's own phase/`is_blocked` (spec 0116 D-8), so a caller that dropped
 * `permissions` would keep rendering the previous state's buttons until a
 * reload.
 */
describe('tasks api — the six domain actions return the refreshed permissions', () => {
  /** Spec 0123 D-1: `time_entry` is now mandatory in the payload, on both completion paths. */
  it('complete posts its payload to /tasks/{id}/complete and echoes permissions', async () => {
    const payload = {
      closure_feedback: 'fatto',
      time_entry: { date: '2026-09-14', task_type_id: 2, minutes: 60 },
    }
    const result = await completeTask(7, payload)

    expect(postMock).toHaveBeenCalledWith('/tasks/7/complete', payload)
    expect(result.permissions).toEqual(PERMISSIONS)
    expect(result.id).toBe(7)
  })

  it('the five body-less actions post with no payload and echo permissions', async () => {
    const results = await Promise.all([
      uncompleteTask(7),
      approveTask(7),
      rejectTask(7),
      blockTask(7),
      unblockTask(7),
    ])

    expect(postMock).toHaveBeenCalledWith('/tasks/7/uncomplete')
    expect(postMock).toHaveBeenCalledWith('/tasks/7/approve')
    expect(postMock).toHaveBeenCalledWith('/tasks/7/reject')
    expect(postMock).toHaveBeenCalledWith('/tasks/7/block')
    expect(postMock).toHaveBeenCalledWith('/tasks/7/unblock')
    for (const result of results) {
      expect(result.permissions).toEqual(PERMISSIONS)
    }
  })
})

/** Spec 0118 D-14: the seventh action's response is the same detail tree as every other one, despite writing nothing on the task. */
describe('tasks api — request-update (D-10..D-14)', () => {
  it('posts recipient_ids and message to /tasks/{id}/request-update and echoes permissions', async () => {
    const result = await requestTaskUpdate(7, { recipient_ids: [31, 41], message: 'a che punto sei?' })

    expect(postMock).toHaveBeenCalledWith('/tasks/7/request-update', {
      recipient_ids: [31, 41],
      message: 'a che punto sei?',
    })
    expect(result.permissions).toEqual(PERMISSIONS)
    expect(result.id).toBe(7)
  })
})
