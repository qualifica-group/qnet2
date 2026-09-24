import { describe, expect, it } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { taskBulkErrorDescription, taskBulkIncompatibleTasks } from '@/features/tasks/task-bulk-error'

function axiosErrorWith(status: number, data: unknown): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status,
    statusText: 'error',
    data,
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

describe('taskBulkIncompatibleTasks (spec 0156 D-6)', () => {
  it('reads the incompatible_tasks list off a 422 response', () => {
    const error = axiosErrorWith(422, {
      success: false,
      message: 'Alcuni task non sono ammessi.',
      incompatible_tasks: [{ id: 7, reason: 'Task bloccato.' }],
    })

    expect(taskBulkIncompatibleTasks(error)).toEqual([{ id: 7, reason: 'Task bloccato.' }])
  })

  it('returns null for a 422 with an empty/absent list', () => {
    expect(taskBulkIncompatibleTasks(axiosErrorWith(422, { success: false, message: 'x' }))).toBeNull()
    expect(taskBulkIncompatibleTasks(axiosErrorWith(422, { success: false, message: 'x', incompatible_tasks: [] }))).toBeNull()
  })

  it('returns null for a non-422 failure (e.g. 403)', () => {
    expect(taskBulkIncompatibleTasks(axiosErrorWith(403, { success: false, message: 'x' }))).toBeNull()
  })

  it('returns null for a non-axios error', () => {
    expect(taskBulkIncompatibleTasks(new Error('network'))).toBeNull()
  })
})

describe('taskBulkErrorDescription', () => {
  it('lists every incompatible task with the server\'s own reason', () => {
    const error = axiosErrorWith(422, {
      success: false,
      message: 'x',
      incompatible_tasks: [
        { id: 7, reason: 'Task bloccato.' },
        { id: 9, reason: 'Fase non aperta.' },
      ],
    })

    const { message, reasons } = taskBulkErrorDescription(i18n.t, error)

    expect(message).toBe(i18n.t('tasks.bulk.incompatibleError', { count: 2 }))
    expect(reasons).toEqual([
      i18n.t('tasks.bulk.incompatibleReason', { id: 7, reason: 'Task bloccato.' }),
      i18n.t('tasks.bulk.incompatibleReason', { id: 9, reason: 'Fase non aperta.' }),
    ])
  })

  it('falls back to the forbidden message on a 403, with no reasons', () => {
    const { message, reasons } = taskBulkErrorDescription(i18n.t, axiosErrorWith(403, { success: false, message: 'x' }))

    expect(message).toBe(i18n.t('tasks.bulk.forbidden'))
    expect(reasons).toEqual([])
  })

  it('falls back to the generic message otherwise', () => {
    const { message, reasons } = taskBulkErrorDescription(i18n.t, new Error('network'))

    expect(message).toBe(i18n.t('tasks.bulk.genericError'))
    expect(reasons).toEqual([])
  })
})
