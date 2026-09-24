import { describe, expect, it } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import { taskAccessDeniedInfo } from '@/features/tasks/task-access-denied-info'

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

describe('taskAccessDeniedInfo (spec 0155 D-7)', () => {
  it('reads the message and contacts off a 403 response', () => {
    const info = taskAccessDeniedInfo(
      axiosErrorWith(403, {
        success: false,
        message: 'Non hai accesso.',
        errors: { access_contacts: [{ id: 1, name: 'Carla Conti', email: 'carla@example.com' }] },
      }),
    )

    expect(info).toEqual({
      message: 'Non hai accesso.',
      contacts: [{ id: 1, name: 'Carla Conti', email: 'carla@example.com' }],
    })
  })

  it('drops a malformed contact entry rather than throwing', () => {
    const info = taskAccessDeniedInfo(
      axiosErrorWith(403, {
        message: 'Non hai accesso.',
        errors: { access_contacts: [{ id: 1, name: 'Carla Conti' }] },
      }),
    )

    expect(info?.contacts).toEqual([])
  })

  it('returns null for a non-403 error', () => {
    expect(taskAccessDeniedInfo(axiosErrorWith(500, {}))).toBeNull()
  })

  it('returns null for a non-axios error', () => {
    expect(taskAccessDeniedInfo(new Error('boom'))).toBeNull()
  })
})
