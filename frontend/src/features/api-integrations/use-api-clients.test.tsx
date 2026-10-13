// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { createWrapper, buildClient } from '@/features/api-integrations/test-support'
import { apiIntegrationsKeys } from '@/features/api-integrations/query-keys'
import { useCreateApiClient, useApiClient } from '@/features/api-integrations/use-api-clients'

const api = vi.hoisted(() => ({ createApiClient: vi.fn(), fetchApiClient: vi.fn() }))
vi.mock('@/features/api-integrations/api', () => api)

beforeEach(() => {
  api.createApiClient.mockReset()
  api.fetchApiClient.mockReset()
})

describe('useCreateApiClient', () => {
  it('invalidates the feature queries and releases the mutation (and its key) once reset', async () => {
    api.createApiClient.mockResolvedValue({ client: buildClient(), plain_text_key: 'secret-key' })
    const { client, Wrapper } = createWrapper()
    const invalidate = vi.spyOn(client, 'invalidateQueries')
    const { result } = renderHook(() => useCreateApiClient(), { wrapper: Wrapper })

    const created = await result.current.mutateAsync({ name: 'ERP' })
    result.current.reset()

    expect(created.plain_text_key).toBe('secret-key')
    expect(invalidate).toHaveBeenCalledWith({ queryKey: apiIntegrationsKeys.all })
    await waitFor(() => expect(client.getMutationCache().getAll()).toHaveLength(0))
  })
})

describe('useApiClient', () => {
  it('does not fetch without an id', () => {
    const { Wrapper } = createWrapper()
    renderHook(() => useApiClient(null), { wrapper: Wrapper })
    expect(api.fetchApiClient).not.toHaveBeenCalled()
  })
})
