import type { ReactNode } from 'react'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { RequestReportDialog } from '@/features/request-management/request-report-dialog'
import type { RequestReportCategory, RequestReportRun } from '@/features/request-management/report-api'

/**
 * Spec 0106 AC-042..AC-046, rev-2 AC-049..AC-054: the "Report CSV" dialog,
 * driven entirely through the frozen `/request-management/report` contract.
 * The API module is mocked (same convention as `export-dialog.test.tsx`);
 * every assertion queries by accessible role/label, never `data-testid`.
 */

const createRequestManagementReportMock = vi.fn()
const getRequestManagementReportMock = vi.fn()
const downloadRequestManagementReportMock = vi.fn()
const fetchRequestManagementReportCategoriesMock = vi.fn()

vi.mock('@/features/request-management/report-api', () => ({
  createRequestManagementReport: (...args: unknown[]) => createRequestManagementReportMock(...args),
  getRequestManagementReport: (...args: unknown[]) => getRequestManagementReportMock(...args),
  downloadRequestManagementReport: (...args: unknown[]) => downloadRequestManagementReportMock(...args),
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
}))

/** The two branches most tests load; both selected by default (AC-050). */
const DEFAULT_CATEGORIES: RequestReportCategory[] = [
  { key: 'gol', label: 'GOL' },
  { key: 'consulenza', label: 'Consulenza' },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestManagementReportMock.mockReset()
  getRequestManagementReportMock.mockReset()
  downloadRequestManagementReportMock.mockReset().mockResolvedValue(undefined)
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue(DEFAULT_CATEGORIES)
})

const ORIGINAL_TZ = process.env.TZ

afterEach(() => {
  vi.useRealTimers()
  process.env.TZ = ORIGINAL_TZ
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

function renderDialog(onOpenChange = vi.fn()) {
  render(<RequestReportDialog open onOpenChange={onOpenChange} />, { wrapper: wrapper() })
  return { onOpenChange }
}

function fillDates(from: string, to: string) {
  fireEvent.change(screen.getByLabelText(/^From/), { target: { value: from } })
  fireEvent.change(screen.getByLabelText(/^To/), { target: { value: to } })
}

/** Waits for the branch checkbox group to land — the confirm button stays disabled until then (AC-049). */
async function waitForCategories() {
  return screen.findByRole('checkbox', { name: 'GOL' })
}

describe('RequestReportDialog', () => {
  it('exposes two required, accessibly-labeled date fields (AC-042)', () => {
    renderDialog()

    expect(screen.getByLabelText(/^From/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^To/)).toBeInTheDocument()
  })

  it('prefills the current week\'s Monday/Friday on a midweek Wednesday (AC-056)', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(2026, 8, 9, 12, 0)) // Wednesday

    renderDialog()

    expect(screen.getByLabelText(/^From/)).toHaveValue('2026-09-07')
    expect(screen.getByLabelText(/^To/)).toHaveValue('2026-09-11')
  })

  it('prefills the PRECEDING week\'s Monday/Friday when opened on a Sunday (AC-056)', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(2026, 8, 6, 12, 0)) // Sunday

    renderDialog()

    expect(screen.getByLabelText(/^From/)).toHaveValue('2026-08-31')
    expect(screen.getByLabelText(/^To/)).toHaveValue('2026-09-04')
  })

  it('uses local date components, not toISOString(), just after midnight east of UTC (AC-057)', () => {
    process.env.TZ = 'Asia/Tokyo'
    vi.useFakeTimers()
    // 2026-09-07 00:30 Tokyo time is genuinely Monday locally; its UTC
    // equivalent is Sunday 2026-09-06 15:30 — `toISOString()` would wrongly
    // propose that Sunday instead.
    vi.setSystemTime(new Date(2026, 8, 7, 0, 30))

    renderDialog()

    expect(screen.getByLabelText(/^From/)).toHaveValue('2026-09-07')
    expect(screen.getByLabelText(/^To/)).toHaveValue('2026-09-11')
  })

  it('blocks submission and shows an accessible error when date_to precedes date_from (AC-043/AC-044)', async () => {
    renderDialog()
    await waitForCategories()

    fillDates('2026-09-30', '2026-09-01')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The end date cannot be earlier than the start date.')

    const dateToField = screen.getByLabelText(/^To/)
    expect(dateToField).toHaveAttribute('aria-invalid', 'true')
    expect(dateToField.getAttribute('aria-describedby')).toContain(alert.id)
    expect(createRequestManagementReportMock).not.toHaveBeenCalled()
  })

  it('runs the full create -> poll -> download cycle and disables confirm meanwhile (AC-045/AC-045-ter)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))
    getRequestManagementReportMock.mockResolvedValue(run({ status: 'completed' }))

    renderDialog()
    await waitForCategories()
    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith({
        date_from: '2026-09-01',
        date_to: '2026-09-30',
        category_keys: ['gol', 'consulenza'],
        row_mode: 'all',
      }),
    )

    // While processing, the confirm affordance is disabled — a second click
    // cannot open a second run.
    expect(screen.getByRole('button', { name: /generating/i })).toBeDisabled()

    await waitFor(() => expect(downloadRequestManagementReportMock).toHaveBeenCalledWith(1), {
      timeout: 3000,
    })
    expect(await screen.findByText(/download started automatically/)).toBeInTheDocument()
  }, 10000)

  it('stops polling and shows a readable error on a failed run, without downloading (AC-045-bis)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))
    getRequestManagementReportMock.mockResolvedValue(run({ status: 'failed' }))

    renderDialog()
    await waitForCategories()
    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Report generation failed. Please try again.',
    )
    expect(downloadRequestManagementReportMock).not.toHaveBeenCalled()
  })

  it('surfaces a localized error on a 403 create response and keeps the dialog open (AC-046)', async () => {
    createRequestManagementReportMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )
    const { onOpenChange } = renderDialog()
    await waitForCategories()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "You don't have permission to generate this report.",
    )
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
    expect(screen.getByLabelText(/^From/)).toBeInTheDocument()
  })

  it('surfaces a localized error on a 422 create response and keeps the dialog open (AC-046)', async () => {
    createRequestManagementReportMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid' },
      } as never),
    )
    const { onOpenChange } = renderDialog()
    await waitForCategories()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('The dates entered are invalid.')
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
    expect(screen.getByLabelText(/^From/)).toBeInTheDocument()
  })

  it('disables confirm while the branch list is loading (AC-049)', async () => {
    let resolveCategories: (categories: RequestReportCategory[]) => void = () => {}
    fetchRequestManagementReportCategoriesMock.mockReturnValue(
      new Promise<RequestReportCategory[]>((resolve) => {
        resolveCategories = resolve
      }),
    )

    renderDialog()

    expect(screen.getByRole('button', { name: 'Generate' })).toBeDisabled()

    resolveCategories(DEFAULT_CATEGORIES)
    await waitFor(() => expect(screen.getByRole('button', { name: 'Generate' })).not.toBeDisabled())
  })

  it('preselects every branch by default (AC-050)', async () => {
    renderDialog()
    await waitForCategories()

    expect(screen.getByRole('checkbox', { name: 'GOL' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).toBeChecked()
  })

  it('shows the select-all control as indeterminate with a partial selection (AC-055)', async () => {
    renderDialog()
    await waitForCategories()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Consulenza' }))

    expect(screen.getByRole('checkbox', { name: 'Select all' })).toBePartiallyChecked()
  })

  it('selects every branch from a partial selection via select-all, never adding a bogus key (AC-055)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))
    renderDialog()
    await waitForCategories()

    // Partial selection first, then let select-all top it back up.
    fireEvent.click(screen.getByRole('checkbox', { name: 'Consulenza' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Select all' }))

    expect(screen.getByRole('checkbox', { name: 'GOL' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Select all' })).toBeChecked()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith(
        expect.objectContaining({ category_keys: ['gol', 'consulenza'] }),
      ),
    )
  })

  it('deselects every branch from a full selection via select-all and falls back to AC-051 (AC-055)', async () => {
    renderDialog()
    await waitForCategories()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select all' }))

    expect(screen.getByRole('checkbox', { name: 'GOL' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Select all' })).not.toBeChecked()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Select at least one category.')
    expect(createRequestManagementReportMock).not.toHaveBeenCalled()
  })

  it('blocks submission with an accessible group error when every branch is unchecked (AC-051/AC-054)', async () => {
    fetchRequestManagementReportCategoriesMock.mockResolvedValue([{ key: 'gol', label: 'GOL' }])
    renderDialog()
    const gol = await waitForCategories()

    fireEvent.click(gol)
    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Select at least one category.')

    const group = screen.getByRole('group')
    expect(group).toHaveAttribute('aria-invalid', 'true')
    expect(group.getAttribute('aria-describedby')).toContain(alert.id)
    expect(createRequestManagementReportMock).not.toHaveBeenCalled()
  })

  it('exposes the three row-mode options with "all" preselected and sends the choice (AC-052)', async () => {
    createRequestManagementReportMock.mockResolvedValue(run({ status: 'processing' }))
    renderDialog()
    await waitForCategories()

    expect(screen.getByRole('radio', { name: 'Everything' })).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('radio', { name: 'Total only' })).toHaveAttribute('aria-checked', 'false')
    expect(screen.getByRole('radio', { name: 'Operators only' })).toHaveAttribute('aria-checked', 'false')

    fireEvent.click(screen.getByRole('radio', { name: 'Total only' }))
    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Generate' }))

    await waitFor(() =>
      expect(createRequestManagementReportMock).toHaveBeenCalledWith(
        expect.objectContaining({ row_mode: 'total_only' }),
      ),
    )
  })

  it('shows an explicit message and blocks generation when the branch list is empty (AC-053)', async () => {
    fetchRequestManagementReportCategoriesMock.mockResolvedValue([])
    renderDialog()

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No categories available: there are no visible requests to include in the report.',
    )
    expect(screen.getByRole('button', { name: 'Generate' })).toBeDisabled()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })
})
