import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'
import AiAssistantCostsPage from '@/features/ai-assistant-costs/ai-assistant-costs-page'
import { currentMonthPeriod } from '@/features/ai-assistant-costs/period'
import type { AiCostReport } from '@/features/ai-assistant-costs/types'

/** Spec 0433: the super-admin costs page. The API module is mocked. */

const fetchReportMock = vi.fn()
const hasRoleMock = vi.fn()

vi.mock('@/features/ai-assistant-costs/api', () => ({
  fetchAiCostReport: (period: unknown) => fetchReportMock(period),
}))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: (role: string) => hasRoleMock(role), roles: [], isLoading: false }),
}))
// recharts needs layout: the charts are covered by their own tests.
vi.mock('@/components/ui/trend-line-chart', () => ({
  TrendLineChart: ({ title, months }: { title: string; months: string[] }) => (
    <div role="img" aria-label={title}>{months.join(',')}</div>
  ),
}))
vi.mock('@/components/ui/report-bar-chart', () => ({
  ReportBarChart: ({ title, points }: { title: string; points: { label: string }[] }) => (
    <div role="img" aria-label={title}>{points.map((point) => point.label).join(',')}</div>
  ),
}))

const report: AiCostReport = {
  period: { from: '2026-11-01', to: '2026-11-26' },
  totals: {
    cost_usd: '1.750000000000',
    requests: 12,
    input_tokens: 3400,
    output_tokens: 560,
    cache_read_tokens: 100,
    reasoning_tokens: 20,
  },
  by_day: [
    { date: '2026-11-01', cost_usd: '1.500000000000', requests: 10 },
    { date: '2026-11-26', cost_usd: '0.250000000000', requests: 2 },
  ],
  by_function: [
    { business_function_id: 3, name: 'Sales', cost_usd: '1.200000000000', requests: 8 },
    { business_function_id: null, name: null, cost_usd: '0.550000000000', requests: 4 },
  ],
  by_user: [
    { user_id: 1, name: 'Ada Admin', cost_usd: '1.500000000000', requests: 10, input_tokens: 3000, output_tokens: 500 },
    { user_id: 9, name: null, cost_usd: '0.250000000000', requests: 2, input_tokens: 400, output_tokens: 60 },
  ],
  by_model: [{ model_id: 'acme/text-pro', cost_usd: '1.750000000000', requests: 12, input_tokens: 3400, output_tokens: 560 }],
  caps: { month: '2026-11', spent_total_usd: '12.340000000000', spend_cap_total_usd: '100.000000000000', spend_cap_person_usd: null },
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/dev/ai-assistant-costs']}>
        <Routes>
          <Route element={<MigrationRouteGuard />}>
            <Route path="/dev/ai-assistant-costs" element={children} />
          </Route>
          <Route path="/dashboard" element={<p>dashboard</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('AiAssistantCostsPage', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('en')
    vi.clearAllMocks()
    hasRoleMock.mockReturnValue(true)
    fetchReportMock.mockResolvedValue(report)
  })

  it('redirects a user without the super-admin role and calls no endpoint', async () => {
    hasRoleMock.mockReturnValue(false)

    render(<AiAssistantCostsPage />, { wrapper })

    expect(await screen.findByText('dashboard')).toBeInTheDocument()
    expect(fetchReportMock).not.toHaveBeenCalled()
  })

  it('asks for the current month first and shows totals, charts and tables with dollar figures', async () => {
    render(<AiAssistantCostsPage />, { wrapper })

    expect(await screen.findByText('Cost in the period')).toBeInTheDocument()
    expect(screen.getAllByText('$1.75').length).toBeGreaterThan(0)
    expect(fetchReportMock).toHaveBeenCalledWith(currentMonthPeriod())
    expect(screen.getAllByText('3,400').length).toBeGreaterThan(0)
    expect(screen.getByRole('img', { name: 'Cost per day' })).toHaveTextContent('2026-11-01,2026-11-26')
    expect(screen.getByRole('img', { name: 'Cost per business function' })).toHaveTextContent('Sales,No function')
    const byFunction = screen.getByRole('heading', { name: 'By business function' }).closest('div') as HTMLElement
    expect(within(byFunction).getByText('No function')).toBeInTheDocument()
    expect(within(byFunction).getByText('$0.55')).toBeInTheDocument()
    expect(screen.getByText('Person no longer present')).toBeInTheDocument()
    expect(screen.getByText('acme/text-pro')).toBeInTheDocument()
    expect(screen.getByText('$12.34')).toBeInTheDocument()
    expect(screen.getByText('No cap')).toBeInTheDocument()
  })

  it('asks again when the period changes and never when it is inverted', async () => {
    render(<AiAssistantCostsPage />, { wrapper })
    await screen.findByText('Cost in the period')

    fireEvent.change(screen.getByLabelText('From'), { target: { value: '2026-10-01' } })
    await waitFor(() => expect(fetchReportMock).toHaveBeenLastCalledWith({ ...currentMonthPeriod(), from: '2026-10-01' }))

    const calls = fetchReportMock.mock.calls.length
    fireEvent.change(screen.getByLabelText('To'), { target: { value: '2026-09-01' } })

    expect(await screen.findByText('The start date cannot be after the end date.')).toBeInTheDocument()
    expect(fetchReportMock).toHaveBeenCalledTimes(calls)
  })

  it('tells the costs could not be loaded', async () => {
    fetchReportMock.mockRejectedValue(new Error('403'))

    render(<AiAssistantCostsPage />, { wrapper })

    expect(await screen.findByText('The costs could not be loaded.')).toBeInTheDocument()
  })
})
