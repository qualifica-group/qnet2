import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TableToolbar, type TableToolbarProps } from '@/features/table/table-toolbar'

// Assert against the English catalogue (the app default locale is Italian).
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** Baseline props; each test overrides only what it exercises. */
function baseProps() {
  return {
    rowCount: 50,
    filtersActive: false,
    onResetFilters: vi.fn(),
    resettingFilters: false,
    layoutCustomized: false,
    onResetLayout: vi.fn(),
    resettingLayout: false,
    fullscreen: false,
    onToggleFullscreen: vi.fn(),
  }
}

function renderToolbar(overrides: Partial<TableToolbarProps> = {}) {
  const props = { ...baseProps(), ...overrides }
  render(
    <I18nextProvider i18n={i18n}>
      <TooltipProvider>
        <TableToolbar {...props} />
      </TooltipProvider>
    </I18nextProvider>,
  )
  return props
}

describe('TableToolbar', () => {
  it('shows the live row count and no search field (the search sits above the grid)', () => {
    renderToolbar()

    expect(screen.getByText('50 rows')).toBeInTheDocument()
    expect(screen.queryByRole('searchbox')).not.toBeInTheDocument()
  })

  it('offers the reset-filters control only while filters are active', () => {
    const { onResetFilters } = renderToolbar({ filtersActive: true })

    fireEvent.click(screen.getByRole('button', { name: 'Reset filters' }))
    expect(onResetFilters).toHaveBeenCalledTimes(1)
  })

  it('does not render the reset-filters control when no filter is active', () => {
    renderToolbar({ filtersActive: false })

    expect(screen.queryByRole('button', { name: 'Reset filters' })).not.toBeInTheDocument()
  })

  it('toggles fullscreen and swaps its label', () => {
    const { onToggleFullscreen } = renderToolbar({ fullscreen: true })

    fireEvent.click(screen.getByRole('button', { name: 'Exit fullscreen' }))
    expect(onToggleFullscreen).toHaveBeenCalledTimes(1)
  })

  it('renders importSlot inside the options menu, not as a standalone control', () => {
    renderToolbar({ importSlot: <div role="menuitem">Import</div> })

    expect(screen.queryByRole('menuitem', { name: 'Import' })).not.toBeInTheDocument()

    // Radix' DropdownMenu trigger opens on `pointerdown`, not `click`.
    fireEvent.pointerDown(screen.getByRole('button', { name: 'Table options' }), {
      button: 0,
      ctrlKey: false,
    })

    expect(screen.getByRole('menuitem', { name: 'Import' })).toBeInTheDocument()
  })

  it('renders bulkActionsSlot next to the row count when provided', () => {
    renderToolbar({
      bulkActionsSlot: (
        <button type="button">Delete selected (2)</button>
      ),
    })

    expect(
      screen.getByRole('button', { name: 'Delete selected (2)' }),
    ).toBeInTheDocument()
  })

  it('omits the bulk-actions area when no selection is active', () => {
    renderToolbar()

    expect(
      screen.queryByRole('button', { name: /delete selected/i }),
    ).not.toBeInTheDocument()
  })

  // User request 2026-11-27: the funnel beside the three dots shows/hides the pinned Commesse filters.
  it('renders the funnel beside the options menu, pressed while the filters show', () => {
    const onToggle = vi.fn()
    renderToolbar({ filtersToggle: { open: true, activeCount: 2, onToggle } })

    const funnel = screen.getByRole('button', { name: 'Hide filters' })
    expect(funnel).toHaveAttribute('aria-pressed', 'true')
    expect(funnel.nextElementSibling).toHaveAccessibleName('Table options')

    fireEvent.click(funnel)
    expect(onToggle).toHaveBeenCalledTimes(1)
  })

  it('counts the applied filters on the funnel while they are hidden', () => {
    renderToolbar({ filtersToggle: { open: false, activeCount: 2, onToggle: vi.fn() } })

    expect(screen.getByRole('button', { name: 'Show filters, 2 active filters' })).toHaveAttribute('aria-pressed', 'false')
  })

  it('has no funnel without pinned filters', () => {
    renderToolbar()

    expect(screen.queryByRole('button', { name: /filters$/ })).not.toBeInTheDocument()
  })

  // User request 2026-11-27: the applied filters and "clear all" beside the row count, no row of their own.
  it('renders the filter chips slot beside the row count', () => {
    renderToolbar({ filterChipsSlot: <span>Title: iso</span> })

    expect(screen.getByText('50 rows').parentElement).toContainElement(screen.getByText('Title: iso'))
  })
})
