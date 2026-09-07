import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { appVersionKeys } from '@/features/app-version/query-keys'
import type { DeployedVersions } from '@/features/app-version/types'

const RUNNING_BUILD = 'build-1'

const fetchDeployedVersionsMock = vi.fn()

vi.mock('@/features/app-version/api', () => ({
  fetchDeployedVersions: () => fetchDeployedVersionsMock(),
}))

// The literal is inlined: `vi.mock` factories are hoisted above every top-level
// binding, so they cannot close over RUNNING_BUILD.
vi.mock('@/config/env', () => ({
  env: { buildVersion: 'build-1', versionPollInterval: 60000, isProductionBuild: false },
}))

/**
 * Renders the hook on a fresh module graph and a fresh QueryClient, so neither
 * the session-scoped backend baseline nor the query cache leaks between tests —
 * each case starts from a genuine cold page load.
 */
async function renderUseAppVersion(enabled = true) {
  vi.resetModules()
  const { useAppVersion } = await import('@/features/app-version/use-app-version')

  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )

  return { queryClient, ...renderHook(() => useAppVersion(enabled), { wrapper }) }
}

function probe(versions: Partial<DeployedVersions>) {
  fetchDeployedVersionsMock.mockResolvedValue({
    frontend: null,
    backend: null,
    ...versions,
  })
}

describe('useAppVersion', () => {
  beforeEach(() => {
    fetchDeployedVersionsMock.mockReset()
  })

  it('does not probe at all when disabled', async () => {
    probe({ frontend: 'build-2' })

    const { result } = await renderUseAppVersion(false)

    expect(fetchDeployedVersionsMock).not.toHaveBeenCalled()
    expect(result.current.isOutdated).toBe(false)
  })

  it('stays current while the deployed frontend build matches the running one', async () => {
    probe({ frontend: RUNNING_BUILD, backend: 'be-1' })

    const { result } = await renderUseAppVersion()

    await waitFor(() => expect(fetchDeployedVersionsMock).toHaveBeenCalled())
    expect(result.current.isOutdated).toBe(false)
  })

  it('reports outdated when a newer frontend build is deployed', async () => {
    probe({ frontend: 'build-2' })

    const { result } = await renderUseAppVersion()

    await waitFor(() => expect(result.current.isOutdated).toBe(true))
  })

  it('reports outdated when the backend changes after the first observation', async () => {
    probe({ frontend: RUNNING_BUILD, backend: 'be-1' })
    const { result, queryClient } = await renderUseAppVersion()

    // The baseline is the first payload the poll actually delivered, so wait for
    // it to land before simulating the redeploy.
    await waitFor(() =>
      expect(queryClient.getQueryData(appVersionKeys.deployed)).toBeDefined(),
    )
    expect(result.current.isOutdated).toBe(false)

    // Second poll sees a redeployed API under the same frontend build.
    probe({ frontend: RUNNING_BUILD, backend: 'be-2' })
    await act(() => queryClient.refetchQueries())

    await waitFor(() => expect(result.current.isOutdated).toBe(true))
  })

  it('never prompts on an unknown version: a failed probe is not a new deploy', async () => {
    probe({ frontend: null, backend: null })

    const { result } = await renderUseAppVersion()

    await waitFor(() => expect(fetchDeployedVersionsMock).toHaveBeenCalled())
    expect(result.current.isOutdated).toBe(false)
  })

  it('keeps the baseline frozen: a backend that reverts is still a mismatch', async () => {
    probe({ frontend: RUNNING_BUILD, backend: 'be-1' })
    const { result, queryClient } = await renderUseAppVersion()
    await waitFor(() =>
      expect(queryClient.getQueryData(appVersionKeys.deployed)).toBeDefined(),
    )

    probe({ frontend: RUNNING_BUILD, backend: 'be-2' })
    await act(() => queryClient.refetchQueries())
    await waitFor(() => expect(result.current.isOutdated).toBe(true))

    // A later poll must not silently adopt whatever it sees as the new baseline.
    probe({ frontend: RUNNING_BUILD, backend: 'be-3' })
    await act(() => queryClient.refetchQueries())

    expect(result.current.isOutdated).toBe(true)
  })
})
