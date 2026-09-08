import { render, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { VersionUpdateBanner } from '@/components/version-update-banner'

const toastMock = vi.fn()

vi.mock('sonner', () => ({
  toast: (...args: unknown[]) => toastMock(...args),
}))

const CURRENT_BUILD = '2026-09-08T10:00:00.000Z'
const DEPLOYED_BUILD = '2026-09-08T12:00:00.000Z'

function setCurrentBuildId(buildId: string): void {
  const meta = document.createElement('meta')
  meta.name = 'app-build-id'
  meta.content = buildId
  document.head.appendChild(meta)
}

/** Serves an index.html carrying the given build id, as the deploy would. */
function serveIndexHtml(buildId: string): void {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue({
      ok: true,
      text: () => Promise.resolve(`<html><head><meta name="app-build-id" content="${buildId}" /></head></html>`),
    }),
  )
}

/** The options object the banner passed to `toast`, once it has fired. */
async function updateToastOptions() {
  await waitFor(() => expect(toastMock).toHaveBeenCalled())
  return toastMock.mock.calls[0][1] as {
    action: { onClick: () => void }
  }
}

beforeEach(() => {
  setCurrentBuildId(CURRENT_BUILD)
})

afterEach(() => {
  document.head.querySelectorAll('meta[name="app-build-id"]').forEach((meta) => meta.remove())
  window.sessionStorage.clear()
  vi.unstubAllGlobals()
  toastMock.mockReset()
})

describe('VersionUpdateBanner', () => {
  it('prompts an update when the deployed build differs from the running one', async () => {
    serveIndexHtml(DEPLOYED_BUILD)

    render(<VersionUpdateBanner />)

    await waitFor(() => expect(toastMock).toHaveBeenCalledTimes(1))
  })

  it('stays silent while the deployed build is the running one', async () => {
    serveIndexHtml(CURRENT_BUILD)

    render(<VersionUpdateBanner />)

    await waitFor(() => expect(fetch).toHaveBeenCalled())
    expect(toastMock).not.toHaveBeenCalled()
  })

  it('does not prompt again for a build the user already updated onto', async () => {
    window.sessionStorage.setItem('app-version-update-dismissed-build', DEPLOYED_BUILD)
    serveIndexHtml(DEPLOYED_BUILD)

    render(<VersionUpdateBanner />)

    await waitFor(() => expect(fetch).toHaveBeenCalled())
    expect(toastMock).not.toHaveBeenCalled()
  })

  it('treats a failing request as "no update": a flaky network must not nag', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')))

    render(<VersionUpdateBanner />)

    await waitFor(() => expect(fetch).toHaveBeenCalled())
    expect(toastMock).not.toHaveBeenCalled()
  })

  it('clears the caches and reloads onto the new build when the action is used', async () => {
    serveIndexHtml(DEPLOYED_BUILD)
    const deleteCache = vi.fn().mockResolvedValue(true)
    vi.stubGlobal('caches', {
      keys: vi.fn().mockResolvedValue(['assets-v1']),
      delete: deleteCache,
    })
    const assign = vi.fn()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { href: 'https://app.test/leads', assign },
    })

    render(<VersionUpdateBanner />)
    const options = await updateToastOptions()
    options.action.onClick()

    await waitFor(() => expect(assign).toHaveBeenCalledTimes(1))
    expect(deleteCache).toHaveBeenCalledWith('assets-v1')
    // The reload is cache-busted, and the build it lands on must not prompt again.
    expect(String(assign.mock.calls[0][0])).toContain('__refresh=')
    expect(window.sessionStorage.getItem('app-version-update-dismissed-build')).toBe(
      DEPLOYED_BUILD,
    )
  })
})
