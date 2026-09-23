import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { useModuleStats } from '@/features/stats/use-module-stats'
import type { ModuleStats } from '@/features/stats/types'

/**
 * Spec 0026 AC-007/AC-009 — the one generic panel renders the widgets the
 * backend describes and owns the loading/error/empty states. No request is
 * issued while it is closed. The panel is wrapped in a Radix `Collapsible`
 * for the open/close animation; jsdom never plays the CSS animation the
 * content is classed with, so Presence has no `animation-name` to wait for
 * and mounts/unmounts synchronously with `isOpen` — exactly what these tests
 * rely on.
 */

const fetchModuleStatsMock = vi.fn<() => Promise<ModuleStats>>()

vi.mock('@/features/stats/api', () => ({
  fetchModuleStats: () => fetchModuleStatsMock(),
  moduleStatsQueryKey: (domain: string) => ['stats', domain],
}))

const LEADS_STATS: ModuleStats = {
  widgets: [
    {
      type: 'stat',
      key: 'total',
      label: 'leads.stats.total',
      value: 128,
      format: 'number',
      subtitle: null,
      icon: 'users',
    },
    {
      type: 'stat',
      key: 'with_source',
      label: 'leads.stats.withSource',
      value: 96,
      format: 'number',
      subtitle: null,
      icon: 'megaphone',
    },
    {
      type: 'distribution',
      key: 'by_source',
      label: 'leads.stats.bySource',
      items: [{ key: 'web', label: 'Web', value: 51, color: null }],
      total: 128,
    },
  ],
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return function QueryWrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

function renderPanel(children: ReactNode = <ModuleStatsPanel domain="leads" isOpen />) {
  const Wrapper = wrapper()

  return render(<Wrapper>{children}</Wrapper>)
}

function deferred() {
  let resolve!: (stats: ModuleStats) => void
  const promise = new Promise<ModuleStats>((res) => {
    resolve = res
  })

  return { promise, resolve }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchModuleStatsMock.mockReset()
})

describe('ModuleStatsPanel', () => {
  it('marks its body busy while the widgets load (AC-009)', () => {
    const pending = deferred()
    fetchModuleStatsMock.mockReturnValue(pending.promise)

    renderPanel()

    const region = screen.getByRole('region', { name: 'Module statistics' })
    expect(region.querySelector('[aria-busy="true"]')).not.toBeNull()
  })

  it('renders the widgets the backend describes, keyed to the module domain', async () => {
    fetchModuleStatsMock.mockResolvedValue(LEADS_STATS)

    renderPanel()

    expect(await screen.findByText('Leads')).toBeInTheDocument()
    expect(screen.getByText('128')).toBeInTheDocument()
    expect(screen.getByText('With source')).toBeInTheDocument()
    expect(screen.getByText('96')).toBeInTheDocument()
    expect(screen.getByRole('meter', { name: 'Web' })).toBeInTheDocument()
  })

  it('keeps only the KPI tiles when charts are turned off (spec 0151 D-11)', async () => {
    fetchModuleStatsMock.mockResolvedValue(LEADS_STATS)

    renderPanel(<ModuleStatsPanel domain="leads" isOpen showCharts={false} />)

    expect(await screen.findByText('128')).toBeInTheDocument()
    expect(screen.getByText('96')).toBeInTheDocument()
    expect(screen.queryByRole('meter', { name: 'Web' })).not.toBeInTheDocument()
  })

  it('shows an empty state when the module exposes no widget (AC-009)', async () => {
    fetchModuleStatsMock.mockResolvedValue({ widgets: [] })

    renderPanel()

    expect(
      await screen.findByText('No statistics are available for this module.'),
    ).toBeInTheDocument()
  })

  it('shows an error message and refetches on retry (AC-009)', async () => {
    fetchModuleStatsMock.mockRejectedValueOnce(new AxiosError('failed'))
    fetchModuleStatsMock.mockResolvedValueOnce(LEADS_STATS)

    renderPanel()

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Unable to load the statistics. Please try again.',
    )

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByText('Leads')).toBeInTheDocument()
    expect(fetchModuleStatsMock).toHaveBeenCalledTimes(2)
  })

  it('mounts no request while closed, then issues exactly one on the first open (AC-007)', async () => {
    fetchModuleStatsMock.mockResolvedValue(LEADS_STATS)
    const Wrapper = wrapper()

    const { rerender } = render(<ModuleStatsPanel domain="leads" isOpen={false} />, {
      wrapper: Wrapper,
    })

    expect(screen.queryByRole('region', { name: 'Module statistics' })).not.toBeInTheDocument()
    expect(fetchModuleStatsMock).not.toHaveBeenCalled()

    rerender(<ModuleStatsPanel domain="leads" isOpen />)

    expect(await screen.findByText('Leads')).toBeInTheDocument()
    expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1)
  })
})

describe('useModuleStats', () => {
  it('issues no request while the panel is closed, and exactly one on the first open (AC-007)', async () => {
    fetchModuleStatsMock.mockResolvedValue(LEADS_STATS)
    const Wrapper = wrapper()

    const { rerender } = renderHook(({ isOpen }) => useModuleStats('leads', isOpen), {
      wrapper: Wrapper,
      initialProps: { isOpen: false },
    })

    expect(fetchModuleStatsMock).not.toHaveBeenCalled()

    rerender({ isOpen: true })

    await waitFor(() => expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1))
  })
})
