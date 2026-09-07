import { Component, Suspense, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { lazyRoute } from '@/routes/lazy-route'

function Page() {
  return <p>page</p>
}

/**
 * Minimal boundary so a rethrown import failure is asserted on instead of
 * escaping the render and failing the run: rethrowing is the guarded path's
 * intended behaviour.
 */
class CatchImportError extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError() {
    return { failed: true }
  }

  render() {
    return this.state.failed ? <p>failed</p> : this.props.children
  }
}

function renderRoute(factory: () => Promise<{ default: typeof Page }>) {
  const Route = lazyRoute(factory)
  return render(
    <CatchImportError>
      <Suspense fallback={<p>loading</p>}>
        <Route />
      </Suspense>
    </CatchImportError>,
  )
}

describe('lazyRoute', () => {
  const reload = vi.fn()
  let originalLocation: Location

  beforeEach(() => {
    reload.mockReset()
    sessionStorage.clear()
    originalLocation = window.location
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...window.location, reload },
    })
  })

  afterEach(() => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: originalLocation,
    })
  })

  it('renders the route module on a successful import', async () => {
    renderRoute(() => Promise.resolve({ default: Page }))

    expect(await screen.findByText('page')).toBeInTheDocument()
    expect(reload).not.toHaveBeenCalled()
  })

  it('reloads onto the new build when a chunk is gone after a deploy', async () => {
    renderRoute(() => Promise.reject(new Error('Failed to fetch dynamically imported module')))

    await waitFor(() => expect(reload).toHaveBeenCalledTimes(1))
    // The fallback stays up: the document is being replaced, so the user never
    // sees an error they cannot act on.
    expect(screen.getByText('loading')).toBeInTheDocument()
  })

  it('reloads at most once, so a genuinely broken chunk cannot loop', async () => {
    renderRoute(() => Promise.reject(new Error('boom')))
    await waitFor(() => expect(reload).toHaveBeenCalledTimes(1))

    renderRoute(() => Promise.reject(new Error('boom')))

    // Second failure surfaces the error instead of reloading again.
    expect(await screen.findByText('failed')).toBeInTheDocument()
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('clears the guard once a route loads again, re-arming the next recovery', async () => {
    sessionStorage.setItem('app:chunk-reload', '1')

    renderRoute(() => Promise.resolve({ default: Page }))

    expect(await screen.findByText('page')).toBeInTheDocument()
    expect(sessionStorage.getItem('app:chunk-reload')).toBeNull()
  })
})
