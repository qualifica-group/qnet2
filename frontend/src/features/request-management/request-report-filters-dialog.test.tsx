import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestReportFiltersDialog } from '@/features/request-management/request-report-filters-dialog'
import { requestReportDefaultValues } from '@/features/request-management/request-report-schema'
import type { RequestReportCategory } from '@/features/request-management/report-api'

/**
 * Spec 0106 rev-2 AC-042..AC-044, AC-049..AC-055: the shared filter sheet.
 * Since the user directive of 2026-09-08 it is CONTROLLED — the applied
 * filters arrive through `value` and go back through `onApply` — and it no
 * longer runs the report: generating the CSV moved next to the "Filters"
 * button, covered by `request-dashboard-filter-bar.test.tsx`. The branch
 * endpoint is mocked; every assertion queries by accessible role/label, never
 * `data-testid`. The current-week defaults belong to
 * `requestReportDefaultValues` and are covered by
 * `request-report-schema.test.ts` (AC-056/AC-057).
 */

const fetchRequestManagementReportCategoriesMock = vi.fn()
const fetchRequestManagementReportOperatorsMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
  fetchRequestManagementReportOperators: (...args: unknown[]) =>
    fetchRequestManagementReportOperatorsMock(...args),
}))

/** The two branches most tests load; both applied by default (AC-050 seeding lives in the panel). */
const DEFAULT_CATEGORIES: RequestReportCategory[] = [
  { key: 'gol', label: 'GOL' },
  { key: 'consulenza', label: 'Consulenza' },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue(DEFAULT_CATEGORIES)
  // Spec 0109: no GA2 by default, so the operator group stays out of the way
  // of the branch-group assertions; the tests that need it opt in.
  fetchRequestManagementReportOperatorsMock.mockReset().mockResolvedValue([])
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderDialog(onOpenChange = vi.fn(), value = requestReportDefaultValues(['gol', 'consulenza'])) {
  const onApply = vi.fn()
  render(<RequestReportFiltersDialog open onOpenChange={onOpenChange} value={value} onApply={onApply} />, {
    wrapper: wrapper(),
  })
  return { onOpenChange, onApply }
}

function fillDates(from: string, to: string) {
  fireEvent.change(screen.getByLabelText(/^From/), { target: { value: from } })
  fireEvent.change(screen.getByLabelText(/^To/), { target: { value: to } })
}

/** Waits for the branch checkbox group to land — apply stays disabled until then (AC-049). */
async function waitForCategories() {
  return screen.findByRole('checkbox', { name: 'GOL' })
}

describe('RequestReportFiltersDialog', () => {
  it('exposes two required, accessibly-labeled date fields (AC-042)', () => {
    renderDialog()

    expect(screen.getByLabelText(/^From/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^To/)).toBeInTheDocument()
  })

  it('opens on the filters the dashboard has applied, not on its own defaults', async () => {
    renderDialog(vi.fn(), {
      date_from: '2026-03-02',
      date_to: '2026-03-06',
      category_keys: ['consulenza'],
      row_mode: 'total_only',
      operator_keys: [],
    })
    await waitForCategories()

    expect(screen.getByLabelText(/^From/)).toHaveValue('2026-03-02')
    expect(screen.getByLabelText(/^To/)).toHaveValue('2026-03-06')
    expect(screen.getByRole('checkbox', { name: 'GOL' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Total only' })).toHaveAttribute('aria-checked', 'true')
  })

  it('hands the edited filters back and closes', async () => {
    const onOpenChange = vi.fn()
    const { onApply } = renderDialog(onOpenChange)
    await waitForCategories()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      // `operator_keys` joined the form values in spec 0109; empty here
      // because this test's picker offers no GA2.
      expect(onApply).toHaveBeenCalledWith({
        date_from: '2026-09-01',
        date_to: '2026-09-30',
        category_keys: ['gol', 'consulenza'],
        row_mode: 'all',
        operator_keys: [],
      }),
    )
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('blocks apply and shows an accessible error when date_to precedes date_from (AC-043/AC-044)', async () => {
    const { onApply } = renderDialog()
    await waitForCategories()

    fillDates('2026-09-30', '2026-09-01')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The end date cannot be earlier than the start date.')

    const dateToField = screen.getByLabelText(/^To/)
    expect(dateToField).toHaveAttribute('aria-invalid', 'true')
    expect(dateToField.getAttribute('aria-describedby')).toContain(alert.id)
    expect(onApply).not.toHaveBeenCalled()
  })

  it('disables apply while the branch list is loading (AC-049)', async () => {
    let resolveCategories: (categories: RequestReportCategory[]) => void = () => {}
    fetchRequestManagementReportCategoriesMock.mockReturnValue(
      new Promise<RequestReportCategory[]>((resolve) => {
        resolveCategories = resolve
      }),
    )

    renderDialog()

    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled()

    resolveCategories(DEFAULT_CATEGORIES)
    await waitFor(() => expect(screen.getByRole('button', { name: 'Apply' })).not.toBeDisabled())
  })

  it('renders the branch selection it was handed as checked (AC-050)', async () => {
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
    const { onApply } = renderDialog()
    await waitForCategories()

    // Partial selection first, then let select-all top it back up.
    fireEvent.click(screen.getByRole('checkbox', { name: 'Consulenza' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Select all' }))

    expect(screen.getByRole('checkbox', { name: 'GOL' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Select all' })).toBeChecked()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(onApply).toHaveBeenCalledWith(expect.objectContaining({ category_keys: ['gol', 'consulenza'] })),
    )
  })

  it('deselects every branch from a full selection via select-all and falls back to AC-051 (AC-055)', async () => {
    const { onApply } = renderDialog()
    await waitForCategories()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select all' }))

    expect(screen.getByRole('checkbox', { name: 'GOL' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Consulenza' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Select all' })).not.toBeChecked()

    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Select at least one category.')
    expect(onApply).not.toHaveBeenCalled()
  })

  it('blocks apply with an accessible group error when every branch is unchecked (AC-051/AC-054)', async () => {
    fetchRequestManagementReportCategoriesMock.mockResolvedValue([{ key: 'gol', label: 'GOL' }])
    const { onApply } = renderDialog(vi.fn(), requestReportDefaultValues(['gol']))
    const gol = await waitForCategories()

    fireEvent.click(gol)
    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Select at least one category.')

    const group = screen.getByRole('group')
    expect(group).toHaveAttribute('aria-invalid', 'true')
    expect(group.getAttribute('aria-describedby')).toContain(alert.id)
    expect(onApply).not.toHaveBeenCalled()
  })

  it('exposes the three row-mode options with "all" preselected and applies the choice (AC-052)', async () => {
    const { onApply } = renderDialog()
    await waitForCategories()

    expect(screen.getByRole('radio', { name: 'Everything' })).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('radio', { name: 'Total only' })).toHaveAttribute('aria-checked', 'false')
    expect(screen.getByRole('radio', { name: 'Operators only' })).toHaveAttribute('aria-checked', 'false')

    fireEvent.click(screen.getByRole('radio', { name: 'Total only' }))
    fillDates('2026-09-01', '2026-09-30')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(onApply).toHaveBeenCalledWith(expect.objectContaining({ row_mode: 'total_only' })),
    )
  })

  it('shows an explicit message and blocks apply when the branch list is empty (AC-053)', async () => {
    fetchRequestManagementReportCategoriesMock.mockResolvedValue([])
    renderDialog()

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No categories available: there are no visible requests to include in the report.',
    )
    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })
})
