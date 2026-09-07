import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchDeployedVersions } from '@/features/app-version/api'

const axiosGetMock = vi.fn()
const apiGetMock = vi.fn()

vi.mock('axios', () => ({
  default: { get: (...args: unknown[]) => axiosGetMock(...args) },
}))

vi.mock('@/api/client', () => ({
  apiClient: { get: (...args: unknown[]) => apiGetMock(...args) },
}))

describe('fetchDeployedVersions', () => {
  beforeEach(() => {
    axiosGetMock.mockReset()
    apiGetMock.mockReset()
  })

  it('reads both deployed versions', async () => {
    axiosGetMock.mockResolvedValue({ data: { version: 'fe-1' } })
    apiGetMock.mockResolvedValue({ data: { data: { version: 'be-1' } } })

    await expect(fetchDeployedVersions()).resolves.toEqual({
      frontend: 'fe-1',
      backend: 'be-1',
    })
  })

  it('busts the cache on the manifest request', async () => {
    axiosGetMock.mockResolvedValue({ data: { version: 'fe-1' } })
    apiGetMock.mockResolvedValue({ data: { data: { version: 'be-1' } } })

    await fetchDeployedVersions()

    expect(axiosGetMock).toHaveBeenCalledWith(
      '/version.json',
      expect.objectContaining({
        params: expect.objectContaining({ t: expect.any(Number) }),
        headers: { 'Cache-Control': 'no-store' },
      }),
    )
  })

  it('degrades a failing probe to null without failing the other one', async () => {
    axiosGetMock.mockRejectedValue(new Error('offline'))
    apiGetMock.mockResolvedValue({ data: { data: { version: 'be-1' } } })

    await expect(fetchDeployedVersions()).resolves.toEqual({
      frontend: null,
      backend: 'be-1',
    })
  })

  it('rejects a non-manifest payload, as served by an SPA history fallback', async () => {
    axiosGetMock.mockResolvedValue({ data: '<!doctype html><html></html>' })
    apiGetMock.mockResolvedValue({ data: { data: { version: null } } })

    await expect(fetchDeployedVersions()).resolves.toEqual({
      frontend: null,
      backend: null,
    })
  })
})
