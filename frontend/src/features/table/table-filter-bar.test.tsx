import { createRef } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { SEARCH_MAX_LENGTH, TableFilterBar, type TableFilterBarProps } from '@/features/table/table-filter-bar'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

// Hidden descriptor: the panel renders only its Reset/Apply bar, enough to tell open from closed.
const DESCRIPTOR: AdvancedFilterDescriptor = {
  name: 'status',
  label: 'tasks.advancedFilters.status',
  type: 'select',
  order: 1,
  required: false,
  visible: false,
  width: 'md',
  multiple: false,
}

function advancedFilters(activeCount = 0): UseAdvancedFiltersResult {
  return {
    draft: {},
    setFieldValue: vi.fn(),
    isFieldDisabled: () => false,
    isFieldInvalid: () => false,
    dependencyParamsFor: () => undefined,
    canApply: true,
    activeValues: {},
    activeCount,
    requestValues: {},
    apply: vi.fn(),
    reset: vi.fn(),
    clearField: vi.fn(),
  } as unknown as UseAdvancedFiltersResult
}

function renderBar(overrides: Partial<TableFilterBarProps> = {}) {
  const props: TableFilterBarProps = {
    searchEnabled: true,
    searchPlaceholder: 'Search name/email…',
    searchInputRef: createRef<HTMLInputElement>(),
    searchValue: '',
    onSearchChange: vi.fn(),
    searchShortcut: '⌘K',
    advancedFilterDescriptors: [DESCRIPTOR],
    advancedFilters: advancedFilters(),
    advancedFiltersOpen: false,
    onToggleAdvancedFilters: vi.fn(),
    ...overrides,
  }
  const view = render(
    <I18nextProvider i18n={i18n}>
      <TableFilterBar {...props} />
    </I18nextProvider>,
  )
  return { props, ...view }
}

describe('TableFilterBar', () => {
  it('renders nothing when the domain has neither a search nor advanced filters', () => {
    const { container } = renderBar({ searchEnabled: false, advancedFilterDescriptors: [] })

    expect(container).toBeEmptyDOMElement()
  })

  it('shows the search field, capped at the server-side max length', () => {
    renderBar()

    expect(screen.getByPlaceholderText('Search name/email…')).toHaveAttribute('maxlength', String(SEARCH_MAX_LENGTH))
  })

  it('emits every keystroke through onSearchChange', () => {
    const { props } = renderBar()

    fireEvent.change(screen.getByPlaceholderText('Search name/email…'), { target: { value: 'ann' } })

    expect(props.onSearchChange).toHaveBeenCalledWith('ann')
  })

  it('hides the search field when the domain has no searchable columns', () => {
    renderBar({ searchEnabled: false })

    expect(screen.queryByPlaceholderText('Search name/email…')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Advanced filters' })).toBeInTheDocument()
  })

  it('renders the saved-filters control next to the search', () => {
    renderBar({ searchEnabled: true, advancedFilterDescriptors: [], savedViewsSlot: <button type="button">Saved filters</button> })

    expect(screen.getByPlaceholderText('Search name/email…')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Saved filters' })).toBeInTheDocument()
  })

  it('renders with only the saved-filters control', () => {
    renderBar({ searchEnabled: false, advancedFilterDescriptors: [], savedViewsSlot: <button type="button">Saved filters</button> })

    expect(screen.getByRole('button', { name: 'Saved filters' })).toBeInTheDocument()
  })

  it('hides the advanced-filters toggle when the domain declares none', () => {
    renderBar({ advancedFilterDescriptors: [] })

    expect(screen.queryByRole('button', { name: /Advanced filters/ })).not.toBeInTheDocument()
  })

  it('keeps the panel closed until the toggle opens it', () => {
    const { props } = renderBar()

    const toggle = screen.getByRole('button', { name: 'Advanced filters' })
    expect(toggle).toHaveAttribute('aria-pressed', 'false')
    expect(screen.queryByRole('button', { name: 'Apply' })).not.toBeInTheDocument()

    fireEvent.click(toggle)
    expect(props.onToggleAdvancedFilters).toHaveBeenCalledTimes(1)
  })

  it('shows the panel when open', () => {
    renderBar({ advancedFiltersOpen: true })

    expect(screen.getByRole('button', { name: 'Advanced filters' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: 'Apply' })).toBeInTheDocument()
  })

  it('shows the active advanced-filters count even while the panel is closed (AC-012)', () => {
    renderBar({ advancedFilters: advancedFilters(3) })

    expect(screen.getByRole('button', { name: 'Advanced filters, 3 active filters' })).toHaveAttribute('aria-pressed', 'false')
  })
})
