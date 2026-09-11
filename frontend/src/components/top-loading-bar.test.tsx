import { render, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { TopLoadingBar } from '@/components/top-loading-bar'

const navigation = { state: 'idle' }

vi.mock('react-router-dom', () => ({
  useNavigation: () => navigation,
}))

function createClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
}

/** Keeps a query in flight until the test resolves it. */
function deferred() {
  let resolve!: () => void
  const promise = new Promise<string>((res) => {
    resolve = () => res('done')
  })
  return { promise, resolve }
}

function Probe({ promise }: { promise: Promise<string> }) {
  useQuery({ queryKey: ['probe'], queryFn: () => promise })
  return null
}

function bar(container: HTMLElement) {
  return container.querySelector('[data-slot="top-loading-bar"]')
}

/** The filled portion of the bar, whose width/opacity carry the animation. */
function fill(container: HTMLElement) {
  return container.querySelector('[data-slot="top-loading-bar"] > div')
}

beforeEach(() => {
  navigation.state = 'idle'
})

describe('TopLoadingBar', () => {
  it('renders nothing while the app is idle', () => {
    const { container } = render(
      <QueryClientProvider client={createClient()}>
        <TopLoadingBar />
      </QueryClientProvider>,
    )

    expect(bar(container)).toBeNull()
  })

  it('shows while a query is in flight and hides once it settles', async () => {
    const { promise, resolve } = deferred()
    const { container } = render(
      <QueryClientProvider client={createClient()}>
        <TopLoadingBar />
        <Probe promise={promise} />
      </QueryClientProvider>,
    )

    await waitFor(() => expect(bar(container)).not.toBeNull())

    resolve()

    await waitFor(() => expect(bar(container)).toBeNull())
  })

  it('reaches the full width while still opaque when the work resolves instantly', async () => {
    const { promise, resolve } = deferred()
    const { container } = render(
      <QueryClientProvider client={createClient()}>
        <TopLoadingBar />
        <Probe promise={promise} />
      </QueryClientProvider>,
    )

    await waitFor(() => expect(bar(container)).not.toBeNull())

    resolve()

    // Completing and fading in the same commit makes the bar disappear
    // mid-travel: the full width has to be painted while it is still visible.
    await waitFor(() => expect(fill(container)).toHaveStyle({ width: '100%', opacity: '1' }))
  })

  it('shows during a router navigation with no pending request', async () => {
    navigation.state = 'loading'
    const { container } = render(
      <QueryClientProvider client={createClient()}>
        <TopLoadingBar />
      </QueryClientProvider>,
    )

    await waitFor(() => expect(bar(container)).not.toBeNull())
  })
})
