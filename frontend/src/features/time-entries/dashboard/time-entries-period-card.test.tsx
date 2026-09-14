import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TimeEntriesPeriodCard } from '@/features/time-entries/dashboard/time-entries-period-card'
import { useTimeEntriesFilters } from '@/features/time-entries/use-time-entries-filters'
import { getPeriodRange, shiftAnchor, formatPeriodLabel } from '@/features/time-entries/time-entry-period'
import type { TimeEntriesListMeta } from '@/features/time-entries/types'

/**
 * Spec 0122 AC-032 (period navigation), AC-033 (filter chips) and AC-039
 * (export menu + monthly dialog blocking). `TimeEntriesStatsPanels` (AC-034)
 * is covered by its own test file.
 */

const fetchFilteredExportMock = vi.fn()
const fetchMonthlyExportMock = vi.fn()
vi.mock('@/features/time-entries/api', () => ({
  fetchFilteredTimeEntriesExport: (...args: unknown[]) => fetchFilteredExportMock(...args),
  fetchMonthlyTimeEntriesExport: (...args: unknown[]) => fetchMonthlyExportMock(...args),
}))

const saveBlobMock = vi.fn()
vi.mock('@/lib/download', () => ({
  saveBlob: (...args: unknown[]) => saveBlobMock(...args),
  filenameFromContentDisposition: () => null,
}))

const META: TimeEntriesListMeta = {
  selected_user: { id: 7, name: 'Mario Rossi' },
  daily_target_minutes: 480,
  can_write: true,
  can_filter_users: true,
  can_export: true,
  can_export_monthly: true,
  team: { can_view: true, is_full_list: false, members_count: 3 },
}

/** Radix' `DropdownMenu` trigger opens on `pointerdown`, not `click` (see `row-actions.test.tsx`). */
function openDropdownMenu(name: string) {
  fireEvent.pointerDown(screen.getByRole('button', { name }), { button: 0, ctrlKey: false })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  fetchFilteredExportMock.mockReset().mockResolvedValue({ blob: new Blob(['x']), fileName: 'export.xlsx' })
  fetchMonthlyExportMock.mockReset().mockResolvedValue({ blob: new Blob(['x']), fileName: 'monthly.xlsx' })
  saveBlobMock.mockReset()
})

function Harness({ meta = META }: { meta?: TimeEntriesListMeta }) {
  const filters = useTimeEntriesFilters()
  return <TimeEntriesPeriodCard filters={filters} meta={meta} />
}

function renderCard(meta?: TimeEntriesListMeta) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<Harness meta={meta} />, {
    wrapper: ({ children }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

describe('TimeEntriesPeriodCard — period navigation (AC-032)', () => {
  it('advances to the next week, updates the label and the export params', async () => {
    renderCard()

    const today = new Date()
    const currentRange = getPeriodRange('week', today)
    const nextAnchor = shiftAnchor('week', today, 1)
    const nextRange = getPeriodRange('week', nextAnchor)
    const nextLabel = formatPeriodLabel('week', nextAnchor, 'en')

    expect(screen.getByText(formatPeriodLabel('week', today, 'en'))).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Next period' }))

    expect(screen.getByText(nextLabel)).toBeInTheDocument()
    expect(currentRange.from).not.toBe(nextRange.from)

    openDropdownMenu('Export options')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Export filtered report' }))

    await waitFor(() =>
      expect(fetchFilteredExportMock).toHaveBeenCalledWith(
        expect.objectContaining({ date_from: nextRange.from, date_to: nextRange.to }),
      ),
    )
  })

  it('"Today" returns to the preset-relative current period', () => {
    renderCard()
    const today = new Date()

    fireEvent.click(screen.getByRole('button', { name: 'Next period' }))
    expect(screen.getByRole('button', { name: 'Today' })).not.toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: 'Today' }))

    expect(screen.getByText(formatPeriodLabel('week', today, 'en'))).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Today' })).toBeDisabled()
  })
})

describe('TimeEntriesPeriodCard — filter chips (AC-033)', () => {
  /** Opens the drawer, turns on the "Over target" toggle, then closes it (Escape). */
  async function applyOverTargetFilter() {
    fireEvent.click(screen.getByRole('button', { name: 'Time tracking filters' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Target status' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Over target' }))
    await screen.findByText('Target status: Over target')

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape', code: 'Escape' })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  }

  it('shows a chip and the trigger badge, removes the chip, then resets from the bar', async () => {
    renderCard()

    // Radix' Dialog hides the rest of the page (`aria-hidden`) while the drawer
    // is open, so the chip/badge/reset in the card's own header are only
    // queryable once it is closed again.
    await applyOverTargetFilter()

    expect(screen.getByText('Target status: Over target')).toBeInTheDocument()
    const trigger = screen.getByRole('button', { name: 'Time tracking filters' })
    expect(within(trigger).getByText('1')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Remove Target status: Over target' }))
    await waitFor(() => expect(screen.queryByText('Target status: Over target')).not.toBeInTheDocument())

    await applyOverTargetFilter()
    expect(screen.getByText('Target status: Over target')).toBeInTheDocument()

    // The drawer's own "Reset" (inside the dialog) is now unmounted — this is
    // unambiguously the chips bar's.
    fireEvent.click(screen.getByRole('button', { name: 'Reset' }))

    await waitFor(() => expect(screen.queryByText('Target status: Over target')).not.toBeInTheDocument())
    expect(within(screen.getByRole('button', { name: 'Time tracking filters' })).queryByText('1')).not.toBeInTheDocument()
  })

  it('disables the "Utente" picker when the actor cannot filter by user', async () => {
    renderCard({ ...META, can_filter_users: false })

    fireEvent.click(screen.getByRole('button', { name: 'Time tracking filters' }))
    fireEvent.click(await screen.findByRole('button', { name: 'User' }))

    expect(screen.getByRole('combobox', { name: 'User' })).toBeDisabled()
  })
})

describe('TimeEntriesPeriodCard — export menu (AC-039)', () => {
  it('hides both export items when neither permission is granted', () => {
    renderCard({ ...META, can_export: false, can_export_monthly: false })
    expect(screen.queryByRole('button', { name: 'Export options' })).not.toBeInTheDocument()
  })

  it('disables the filtered export item without the export permission', () => {
    renderCard({ ...META, can_export: false })
    openDropdownMenu('Export options')
    expect(screen.getByRole('menuitem', { name: 'Export filtered report' })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('blocks the monthly export when no user is selected', () => {
    renderCard({ ...META, selected_user: undefined as unknown as TimeEntriesListMeta['selected_user'] })
    openDropdownMenu('Export options')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Export monthly report' }))

    expect(screen.getByRole('alert')).toHaveTextContent('Select a user before exporting the monthly report.')
    expect(screen.getByRole('button', { name: 'Export' })).toBeDisabled()
  })

  it('blocks the monthly export on a future period', () => {
    renderCard()
    openDropdownMenu('Export options')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Export monthly report' }))

    fireEvent.change(screen.getByLabelText('Year'), { target: { value: String(new Date().getFullYear() + 1) } })

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Future months are not allowed for monthly report export.',
    )
    expect(screen.getByRole('button', { name: 'Export' })).toBeDisabled()
  })

  it('downloads the monthly report using the response filename', async () => {
    renderCard()
    openDropdownMenu('Export options')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Export monthly report' }))

    fireEvent.click(screen.getByRole('button', { name: 'Export' }))

    await waitFor(() => expect(saveBlobMock).toHaveBeenCalledWith(expect.any(Blob), 'monthly.xlsx'))
    expect(fetchMonthlyExportMock).toHaveBeenCalledWith(
      expect.objectContaining({ user_ids: [META.selected_user.id] }),
    )
  })
})
