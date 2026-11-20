import { afterEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { apiClient } from '@/api/client'
import {
  CONTRACTED_EDIT_CONFIRMATION_CODE,
  isContractedEditDeclined,
  setContractedEditConfirmer,
} from '@/api/contracted-edit'

/** An adapter answering 409 on the first call, then 200; records every sent body. */
function conflictThenOkAdapter(sentBodies: unknown[]) {
  let calls = 0
  return async (config: InternalAxiosRequestConfig) => {
    sentBodies.push(config.data)
    calls += 1
    if (calls === 1) {
      throw new AxiosError('Conflict', '409', config, undefined, {
        status: 409,
        statusText: 'Conflict',
        headers: {},
        config,
        data: { success: false, message: 'x', code: CONTRACTED_EDIT_CONFIRMATION_CODE },
      })
    }
    return { data: { ok: true }, status: 200, statusText: 'OK', headers: {}, config }
  }
}

afterEach(() => setContractedEditConfirmer(null))

describe('contracted edit confirmation (spec 0404 E-D4)', () => {
  it('resends the same request with confirm_contracted_edit once confirmed', async () => {
    const sent: unknown[] = []
    setContractedEditConfirmer(vi.fn().mockResolvedValue(true))

    const response = await apiClient.patch('/quotes/1', { title: 'New' }, { adapter: conflictThenOkAdapter(sent) })

    expect(response.data).toEqual({ ok: true })
    expect(sent).toHaveLength(2)
    expect(JSON.parse(sent[1] as string)).toEqual({ title: 'New', confirm_contracted_edit: true })
  })

  it('rejects with a declined marker and sends nothing more when the user cancels', async () => {
    const sent: unknown[] = []
    setContractedEditConfirmer(vi.fn().mockResolvedValue(false))

    const failure = await apiClient
      .patch('/quotes/1', { title: 'New' }, { adapter: conflictThenOkAdapter(sent) })
      .catch((error: unknown) => error)

    expect(isContractedEditDeclined(failure)).toBe(true)
    expect(sent).toHaveLength(1)
  })

  it('does not ask when no confirmer is mounted', async () => {
    const failure = await apiClient
      .patch('/quotes/1', { title: 'New' }, { adapter: conflictThenOkAdapter([]) })
      .catch((error: unknown) => error)

    expect(isContractedEditDeclined(failure)).toBe(false)
    expect((failure as AxiosError).response?.status).toBe(409)
  })
})
