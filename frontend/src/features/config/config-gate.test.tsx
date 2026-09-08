import { act, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import {
  APP_SPLASH_EXIT_DURATION_MS,
  APP_SPLASH_MIN_DURATION_MS,
} from '@/components/app-splash-screen'
import { ConfigGate } from '@/features/config/config-gate'
import { useConfig } from '@/features/config/use-config'

// The gate's only job is to read the config query state and decide what to
// mount, so we drive it by mocking useConfig rather than wiring a real client.
vi.mock('@/features/config/use-config', () => ({
  useConfig: vi.fn(),
}))

const mockedUseConfig = vi.mocked(useConfig)

type ConfigQueryResult = ReturnType<typeof useConfig>

function mockConfigState(state: Partial<ConfigQueryResult>): void {
  mockedUseConfig.mockReturnValue(state as ConfigQueryResult)
}

const SENTINEL = 'protected-children'

function renderGate() {
  return render(
    <ConfigGate>
      <div>{SENTINEL}</div>
    </ConfigGate>,
  )
}

/** Runs the splash timers (minimum duration, then the exit curtain). */
async function completeSplash(): Promise<void> {
  await act(async () => {
    vi.advanceTimersByTime(APP_SPLASH_MIN_DURATION_MS)
  })
  await act(async () => {
    vi.advanceTimersByTime(APP_SPLASH_EXIT_DURATION_MS)
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

afterEach(() => {
  vi.useRealTimers()
})

describe('ConfigGate', () => {
  it('shows the boot splash and withholds children while pending', () => {
    mockConfigState({ isPending: true, isError: false, isSuccess: false })
    renderGate()

    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText(SENTINEL)).not.toBeInTheDocument()
  })

  it('shows the error screen with a retry button and withholds children on error', () => {
    const refetch = vi.fn()
    mockConfigState({ isPending: false, isError: true, isSuccess: false, refetch })
    renderGate()

    expect(screen.getByText('Unable to start the application')).toBeInTheDocument()
    expect(screen.queryByText(SENTINEL)).not.toBeInTheDocument()

    const retry = screen.getByRole('button', { name: 'Retry' })
    fireEvent.click(retry)
    expect(refetch).toHaveBeenCalledTimes(1)
  })

  it('mounts children under the splash as soon as the config has loaded', () => {
    vi.useFakeTimers()
    mockConfigState({ isPending: false, isError: false, isSuccess: true })
    renderGate()

    expect(screen.getByText(SENTINEL)).toBeInTheDocument()
    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('keeps the splash up for its minimum duration, then lifts it away', async () => {
    vi.useFakeTimers()
    mockConfigState({ isPending: false, isError: false, isSuccess: true })
    renderGate()

    // Still covering while the exit animation runs...
    await act(async () => {
      vi.advanceTimersByTime(APP_SPLASH_MIN_DURATION_MS)
    })
    expect(screen.getByRole('status')).toBeInTheDocument()

    // ...and gone once the curtain has finished.
    await act(async () => {
      vi.advanceTimersByTime(APP_SPLASH_EXIT_DURATION_MS)
    })
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByText(SENTINEL)).toBeInTheDocument()
  })

  it('does not start the exit while the config is still pending', async () => {
    vi.useFakeTimers()
    mockConfigState({ isPending: true, isError: false, isSuccess: false })
    renderGate()

    await completeSplash()

    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText(SENTINEL)).not.toBeInTheDocument()
  })
})
