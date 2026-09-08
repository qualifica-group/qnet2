import { createRef } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TableToolbar, type TableToolbarProps } from '@/features/table/table-toolbar'
import { RequestReportSlot } from '@/features/request-management/request-report-slot'

/**
 * AC-040/AC-041: the "Genera report" entry of the Gestione Richieste
 * toolbar's options menu, gated by `request-management.report`. Mounted
 * through the real `TableToolbar` (the actual `importSlot` consumer), same
 * convention as `table-toolbar.test.tsx`'s own importSlot coverage.
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    isLoading: false,
  }),
}))

const createRequestManagementReportMock = vi.fn()
const fetchRequestManagementReportCategoriesMock = vi.fn()
vi.mock('@/features/request-management/report-api', () => ({
  createRequestManagementReport: (...args: unknown[]) => createRequestManagementReportMock(...args),
  getRequestManagementReport: vi.fn(),
  downloadRequestManagementReport: vi.fn(),
  fetchRequestManagementReportCategories: (...args: unknown[]) =>
    fetchRequestManagementReportCategoriesMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  fetchRequestManagementReportCategoriesMock.mockReset().mockResolvedValue([
    { key: 'gol', label: 'GOL' },
    { key: 'consulenza', label: 'Consulenza' },
  ])
})

/** Baseline props, mirroring `table-toolbar.test.tsx`. */
function baseProps(): TableToolbarProps {
  return {
    searchEnabled: true,
    searchPlaceholder: 'Search',
    searchInputRef: createRef<HTMLInputElement>(),
    searchValue: '',
    onSearchChange: vi.fn(),
    searchShortcut: '⌘K',
    rowCount: 10,
    filtersActive: false,
    onResetFilters: vi.fn(),
    resettingFilters: false,
    layoutCustomized: false,
    onResetLayout: vi.fn(),
    resettingLayout: false,
    fullscreen: false,
    onToggleFullscreen: vi.fn(),
    advancedFiltersEnabled: false,
    advancedFiltersOpen: false,
    onToggleAdvancedFilters: vi.fn(),
    advancedFiltersActiveCount: 0,
    importSlot: <RequestReportSlot />,
  }
}

function renderToolbar() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <I18nextProvider i18n={i18n}>
        <TooltipProvider>
          <TableToolbar {...baseProps()} />
        </TooltipProvider>
      </I18nextProvider>
    </QueryClientProvider>,
  )
}

function openOptionsMenu() {
  // Radix' DropdownMenu trigger opens on `pointerdown`, not `click`.
  fireEvent.pointerDown(screen.getByRole('button', { name: 'Table options' }), {
    button: 0,
    ctrlKey: false,
  })
}

describe('RequestReportSlot (spec 0106)', () => {
  it('is absent without request-management.report (AC-040)', () => {
    canMock.mockReturnValue(false)
    renderToolbar()
    openOptionsMenu()

    expect(screen.queryByRole('menuitem', { name: 'Generate report' })).not.toBeInTheDocument()
  })

  it('is offered with request-management.report (AC-040)', () => {
    canMock.mockReturnValue(true)
    renderToolbar()
    openOptionsMenu()

    expect(screen.getByRole('menuitem', { name: 'Generate report' })).toBeInTheDocument()
  })

  it('opens the dialog and keeps it mounted after selecting the entry (AC-041)', async () => {
    canMock.mockReturnValue(true)
    renderToolbar()
    openOptionsMenu()

    fireEvent.click(screen.getByRole('menuitem', { name: 'Generate report' }))

    expect(screen.getByText('CSV Report')).toBeInTheDocument()
    expect(screen.getByLabelText(/^From/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^To/)).toBeInTheDocument()
    expect(await screen.findByRole('checkbox', { name: 'GOL' })).toBeInTheDocument()
  })
})
