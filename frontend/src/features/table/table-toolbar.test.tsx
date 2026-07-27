import { createRef } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import {
  SEARCH_MAX_LENGTH,
  TableToolbar,
  type TableToolbarProps,
} from '@/features/table/table-toolbar'

// Assert against the English catalogue (the app default locale is Italian).
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** Baseline props; each test overrides only what it exercises. */
function baseProps() {
  return {
    searchEnabled: true,
    searchPlaceholder: 'Search name/email…',
    searchInputRef: createRef<HTMLInputElement>(),
    searchValue: '',
    onSearchChange: vi.fn(),
    searchShortcut: '⌘K',
    rowCount: 50,
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
  it('shows the search field with its placeholder and the live row count', () => {
    renderToolbar()

    expect(screen.getByPlaceholderText('Search name/email…')).toBeInTheDocument()
    expect(screen.getByText('50 rows')).toBeInTheDocument()
  })

  it('hides the search field when the domain has no searchable columns', () => {
    renderToolbar({ searchEnabled: false })

    expect(screen.queryByPlaceholderText('Search name/email…')).not.toBeInTheDocument()
  })

  it('caps the search field at the server-side max length', () => {
    renderToolbar()

    expect(screen.getByPlaceholderText('Search name/email…')).toHaveAttribute(
      'maxlength',
      String(SEARCH_MAX_LENGTH),
    )
  })

  it('emits every keystroke through onSearchChange', () => {
    const { onSearchChange } = renderToolbar()

    fireEvent.change(screen.getByPlaceholderText('Search name/email…'), {
      target: { value: 'ann' },
    })

    expect(onSearchChange).toHaveBeenCalledWith('ann')
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

  it('hides the advanced-filters toggle when the domain declares none', () => {
    renderToolbar({ advancedFiltersEnabled: false })

    expect(
      screen.queryByRole('button', { name: 'Advanced filters' }),
    ).not.toBeInTheDocument()
  })

  it('toggles the advanced-filters panel and reflects its open state (AC-012)', () => {
    const { onToggleAdvancedFilters } = renderToolbar({
      advancedFiltersEnabled: true,
      advancedFiltersOpen: true,
    })

    const toggle = screen.getByRole('button', { name: 'Advanced filters' })
    expect(toggle).toHaveAttribute('aria-pressed', 'true')

    fireEvent.click(toggle)
    expect(onToggleAdvancedFilters).toHaveBeenCalledTimes(1)
  })

  it('shows the active advanced-filters count as a badge even while the panel is closed (AC-012)', () => {
    renderToolbar({
      advancedFiltersEnabled: true,
      advancedFiltersOpen: false,
      advancedFiltersActiveCount: 3,
    })

    const toggle = screen.getByRole('button', { name: 'Advanced filters' })
    expect(toggle).toHaveAttribute('aria-pressed', 'false')
    expect(toggle).toHaveTextContent('3')
  })

  it('renders no badge when there are no active advanced filters', () => {
    renderToolbar({ advancedFiltersEnabled: true, advancedFiltersActiveCount: 0 })

    expect(screen.getByRole('button', { name: 'Advanced filters' })).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
  })
})
