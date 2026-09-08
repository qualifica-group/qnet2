import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { RequestDashboardFilterBar } from '@/features/request-management/request-dashboard-filter-bar'
import {
  requestReportDefaultValues,
  toRequestReportFilterPayload,
} from '@/features/request-management/request-report-schema'
import type { RequestReportRun } from '@/features/request-management/report-api'

/**
 * Spec 0106 AC-040/AC-041, AC-045..AC-046: the report action, which the user
 * directive of 2026-09-08 moved out of the table's options menu and next to
 * the dashboard's "Filters" button. It runs on the APPLIED filters — no form
 * of its own — through the frozen `/request-management/report` contract, and
 * stays gated by `request-management.report`. The format (csv or xlsx) is
 * picked from the action's own menu, like the table export offers. The API
 * module is mocked; every assertion queries by accessible role/label, never
 * `data-testid`.
 */

const createRequestManagementReportMock = vi.fn()
const getRequestManagementReportMock = vi.fn()
const downloadRequestManagementReportMock = vi.fn()

vi.mock('@/features/request-management/report-api', () => ({
  createRequestManagementReport: (...args: unknown[]) => createRequestManagementReportMock(...args),
  getRequestManagementReport: (...args: unknown[]) => getRequestManagementReportMock(...args),
  downloadRequestManagementReport: (...args: unknown[]) => downloadRequestManagementReportMock(...args),
  fetchRequestManagementReportCategories: vi.fn(),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    isLoading: false,
  }),
}))

const APPLIED_FILTERS = {
  ...requestReportDefaultValues(['gol', 'consulenza']),
  date_from: '2026-09-01',
  date_to: '2026-09-30',
}

/**
 * What the panel actually hands the bar (spec 0109 D-9): the NORMALIZED
 * payload, with `operator_keys` already dropped because every operator is
 * selected. The file must be generated from this, not from the raw form
 * values — otherwise the CSV and the charts would read the selection
 * differently.
 */
const APPLIED_PAYLOAD = toRequestReportFilterPayload(APPLIED_FILTERS, [])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockReturnValue(true)
  createRequestManagementReportMock.mockReset()
  getRequestManagementReportMock.mockReset()
  downloadRequestManagementReportMock.mockReset().mockResolvedValue(undefined)
})

function run(overrides: Partial<RequestReportRun> = {}): RequestReportRun {
  return {
    id: 1,
    status: 'processing',
    file_name: 'request-management-report-2026-09-01_2026-09-30.csv',
    created_at: '2026-09-01T00:00:00Z',
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Radix' DropdownMenu trigger opens on `pointerdown`, not `click`. */
function openReportMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: /Generate report/ }), {
    button: 0,
    ctrlKey: false,
  })
}

function generate(format: 'CSV' | 'Excel (XLSX)') {
  openReportMenu()
  fireEvent.click(screen.getByRole('menuitem', { name: format }))
}

function renderBar(filtersReady = true) {
  const onEdit = vi.fn()
  render(
    <RequestDashboardFilterBar
      filters={APPLIED_FILTERS}
      payload={APPLIED_PAYLOAD}
      categoryCount={2}
      operatorCount={0}
      filtersReady={filtersReady}
      onEdit={onEdit}
    />,
    { wrapper: wrapper() },
  )
  return { onEdit }
}

describe('RequestDashboardFilterBar', () => {
  it('summarizes the applied filters and opens the sheet on demand', () => {
    const { onEdit } = renderBar()

    expect(screen.getByText(/01\/09\/2026/)).toHaveTextContent('2/2 categories')
    expect(screen.getByText(/2\/2 categories/)).toHaveTextContent('Everything')

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }))
    expect(onEdit).toHaveBeenCalledTimes(1)
  })

  it('is absent without request-management.report, while the filters stay reachable (AC-040)', () => {
    canMock.mockReturnValue(false)
    renderBar()

    expect(screen.queryByRole('button', { name: /Generate report/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Filters' })).toBeInTheDocument()
  })

  it('runs the full create -> poll -> download cycle on the applied filters (AC-045/AC-045-ter)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))
    getRequestManagementReportMock.mockResolvedValue(run({ status: 'completed' }))

    renderBar()
    generate('CSV')

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith({
        date_from: '2026-09-01',
        date_to: '2026-09-30',
        category_keys: ['gol', 'consulenza'],
        row_mode: 'all',
        format: 'csv',
      }),
    )

    // While processing, the action is disabled — a second click cannot open a
    // second run.
    expect(screen.getByRole('button', { name: /generating/i })).toBeDisabled()

    await waitFor(() => expect(downloadRequestManagementReportMock).toHaveBeenCalledWith(1), {
      timeout: 3000,
    })
    expect(await screen.findByText(/download started automatically/)).toBeInTheDocument()
  }, 10000)

  it('stops polling and shows a readable error on a failed run, without downloading (AC-045-bis)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))
    getRequestManagementReportMock.mockResolvedValue(run({ status: 'failed' }))

    renderBar()
    generate('CSV')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Report generation failed. Please try again.',
    )
    expect(downloadRequestManagementReportMock).not.toHaveBeenCalled()
  })

  it('surfaces a localized error on a 403 create response (AC-046)', async () => {
    createRequestManagementReportMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderBar()
    generate('CSV')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "You don't have permission to generate this report.",
    )
  })

  it('surfaces a localized error on a 422 create response (AC-046)', async () => {
    createRequestManagementReportMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid' },
      } as never),
    )

    renderBar()
    generate('CSV')

    expect(await screen.findByRole('alert')).toHaveTextContent('The dates entered are invalid.')
  })

  it('asks the backend for xlsx when Excel is picked (user directive 2026-09-08)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))

    renderBar()
    generate('Excel (XLSX)')

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith(
        expect.objectContaining({ format: 'xlsx' }),
      ),
    )
  })

  it('cannot generate before the filters are usable (AC-049)', () => {
    renderBar(false)

    expect(screen.getByRole('button', { name: /Generate report/ })).toBeDisabled()
    expect(createRequestManagementReportMock).not.toHaveBeenCalled()
  })
})
