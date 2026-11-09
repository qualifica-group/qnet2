import { afterEach, describe, expect, it, vi } from 'vitest'
import { act, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { apiClient } from '@/api/client'
import { clearMaintenance, reportMaintenance } from '@/api/maintenance-state'
import { fetchAvailability } from '@/features/maintenance/api'
import { MaintenanceGate } from '@/features/maintenance/maintenance-gate'
import '@/i18n'

vi.mock('@/features/maintenance/api', () => ({ fetchAvailability: vi.fn() }))

function renderGate() {
  const queryClient = new QueryClient()
  return render(
    <QueryClientProvider client={queryClient}>
      <MaintenanceGate>
        <input aria-label="draft" defaultValue="typed by the user" />
      </MaintenanceGate>
    </QueryClientProvider>,
  )
}

function requestAnswering503(data: object): Promise<unknown> {
  return act(() =>
    apiClient
      .get('/any', {
        adapter: async (config) => {
          throw new AxiosError('Unavailable', '503', config, undefined, {
            status: 503,
            statusText: 'Service Unavailable',
            headers: {},
            config,
            data,
          })
        },
      })
      .catch(() => undefined),
  )
}

afterEach(() => {
  act(() => clearMaintenance())
  vi.clearAllMocks()
})

describe('MaintenanceGate', () => {
  it('shows only the app when maintenance is off', () => {
    renderGate()

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'draft' })).toBeInTheDocument()
  })

  it('covers the app and makes it inert while the API refuses the caller', async () => {
    vi.mocked(fetchAvailability).mockRejectedValue(new Error('503'))
    renderGate()

    act(() => reportMaintenance())

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'draft' }).closest('[inert]')).not.toBeNull()
  })

  it('opens on a 503 carrying the marker, not on a plain 503', async () => {
    vi.mocked(fetchAvailability).mockRejectedValue(new Error('503'))
    renderGate()

    await requestAnswering503({ success: false, message: 'Timeout' })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()

    await requestAnswering503({ success: false, message: 'Maintenance', maintenance: true })
    expect(await screen.findByRole('alert')).toBeInTheDocument()
  })

  it('closes once the API is back, with the app still mounted', async () => {
    vi.mocked(fetchAvailability).mockResolvedValue(true)
    renderGate()
    const draft = screen.getByRole('textbox', { name: 'draft' })

    act(() => reportMaintenance())

    await waitFor(() => expect(screen.queryByRole('alert')).not.toBeInTheDocument())
    expect(screen.getByRole('textbox', { name: 'draft' })).toBe(draft)
  })
})
