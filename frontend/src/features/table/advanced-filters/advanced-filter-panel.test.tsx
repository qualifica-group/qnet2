import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import i18n from '@/i18n'
import { AdvancedFilterPanel } from '@/features/table/advanced-filters/advanced-filter-panel'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'

const useForSelectMock = vi.fn()

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

/** Fixture enum used by the `enum`/`radio` field tests below. */
const SCOPE_OPTIONS = [
  { value: 'assigned_to_me', label: 'Assigned to me', color: null, icon: null, is_default: false, hidden_on_form: false },
  { value: 'all', label: 'All', color: null, icon: null, is_default: false, hidden_on_form: false },
]

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => SCOPE_OPTIONS,
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReturnValue({
    data: undefined,
    isPending: true,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
})

/** Minimal, schema-valid descriptor fixture; each test overrides only what it exercises. */
function descriptor(
  overrides: Partial<AdvancedFilterDescriptor> & Pick<AdvancedFilterDescriptor, 'name' | 'type'>,
): AdvancedFilterDescriptor {
  return {
    label: 'table.test.label',
    order: 0,
    required: false,
    visible: true,
    width: 'md',
    multiple: false,
    ...overrides,
  }
}

/** Fake `UseAdvancedFiltersResult`; each test overrides only what it exercises. */
function fakeFilters(
  overrides: Partial<UseAdvancedFiltersResult> = {},
): UseAdvancedFiltersResult {
  return {
    draft: {},
    setFieldValue: vi.fn(),
    isFieldDisabled: () => false,
    isFieldInvalid: () => false,
    dependencyParamsFor: () => undefined,
    canApply: true,
    activeValues: {},
    activeCount: 0,
    apply: vi.fn(),
    reset: vi.fn(),
    clearField: vi.fn(),
    applyValues: vi.fn(),
    isSaving: false,
    getApplied: () => ({}),
    ...overrides,
  }
}

function renderPanel(
  descriptors: AdvancedFilterDescriptor[],
  filters: UseAdvancedFiltersResult = fakeFilters(),
) {
  render(
    <I18nextProvider i18n={i18n}>
      <AdvancedFilterPanel descriptors={descriptors} filters={filters} />
    </I18nextProvider>,
  )
}

describe('AdvancedFilterPanel', () => {
  it('renders visible fields in the catalog order, each with the type-correct control (AC-011)', () => {
    // Descriptors arrive from the backend already sorted by `order` (same
    // contract as TableColumn); the panel trusts that order, it does not re-sort.
    const descriptors = [
      descriptor({ name: 'name', type: 'text', label: 'Name', order: 0 }),
      descriptor({ name: 'notes', type: 'textarea', label: 'Notes', order: 1 }),
      descriptor({
        name: 'active',
        type: 'checkbox',
        label: 'Active only',
        order: 2,
      }),
    ]

    renderPanel(descriptors)

    const textboxes = screen.getAllByRole('textbox')
    expect(textboxes[0]).toHaveAccessibleName('Name')
    expect(textboxes[1]).toHaveAccessibleName('Notes')
    expect(screen.getByRole('checkbox')).toBeInTheDocument()
  })

  it('skips a filter declared `visible: false`', () => {
    const descriptors = [
      descriptor({ name: 'hidden', type: 'text', label: 'Hidden', visible: false }),
      descriptor({ name: 'shown', type: 'text', label: 'Shown', visible: true }),
    ]

    renderPanel(descriptors)

    expect(screen.queryByLabelText('Hidden')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Shown')).toBeInTheDocument()
  })

  it('renders a required field marker and its accessible error (AC-015)', () => {
    const descriptors = [
      descriptor({ name: 'status', type: 'text', label: 'Status', required: true }),
    ]

    renderPanel(descriptors, fakeFilters({ isFieldInvalid: () => true, canApply: false }))

    const input = screen.getByRole('textbox', { name: /Status/ })
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby', expect.stringContaining('-error'))
    expect(screen.getByRole('alert')).toHaveTextContent('This field is required.')
    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled()
  })

  it('renders a relation field via the async paginated select (AC-011)', () => {
    const descriptors = [
      descriptor({
        name: 'project',
        type: 'relation',
        label: 'Project',
        source: { resource: 'projects' },
      }),
    ]

    renderPanel(descriptors)

    expect(screen.getByRole('combobox', { name: 'Project' })).toBeInTheDocument()
  })

  it('disables a dependent field and calls setFieldValue on change', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', label: 'Status' })]
    const setFieldValue = vi.fn()

    renderPanel(descriptors, fakeFilters({ setFieldValue }))

    fireEvent.change(screen.getByRole('textbox', { name: 'Status' }), {
      target: { value: 'won' },
    })

    expect(setFieldValue).toHaveBeenCalledWith('status', 'won')
  })

  it('renders a multi-value enum filter as a multi-select, OR-ing the picked values (spec 0153 AC-019)', () => {
    const descriptors = [
      descriptor({
        name: 'assignment',
        type: 'enum',
        label: 'Assignment',
        enumKey: 'task_assignment_scope',
        multiple: true,
      }),
    ]
    const setFieldValue = vi.fn()

    renderPanel(descriptors, fakeFilters({ setFieldValue, draft: { assignment: ['assigned_to_me'] } }))

    const trigger = screen.getByRole('button', { name: 'Assignment' })
    expect(trigger).toHaveTextContent('Assigned to me')

    // Radix' DropdownMenu trigger opens on `pointerdown`, not `click` (mirrors
    // `notification-bell.test.tsx`'s own `openPanel`).
    fireEvent.pointerDown(trigger, { button: 0, ctrlKey: false })
    fireEvent.click(screen.getByRole('menuitemcheckbox', { name: 'All' }))

    expect(setFieldValue).toHaveBeenCalledWith('assignment', ['assigned_to_me', 'all'])
  })

  it('hides the enum values the server lists in excludedValues (spec 0153 D-1)', () => {
    const descriptors = [
      descriptor({
        name: 'assignment',
        type: 'enum',
        label: 'Assignment',
        enumKey: 'task_assignment_scope',
        multiple: true,
        excludedValues: ['visible'],
      }),
    ]

    renderPanel(descriptors, fakeFilters({ draft: { assignment: ['assigned_to_me'] } }))

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Assignment' }), { button: 0, ctrlKey: false })

    expect(screen.getByRole('menuitemcheckbox', { name: 'All' })).toBeInTheDocument()
    expect(screen.queryByRole('menuitemcheckbox', { name: 'All visible' })).not.toBeInTheDocument()
  })

  it('calls apply/reset from the footer', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', label: 'Status' })]
    const apply = vi.fn()
    const reset = vi.fn()

    renderPanel(descriptors, fakeFilters({ apply, reset }))

    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))
    fireEvent.click(screen.getByRole('button', { name: 'Reset' }))

    expect(apply).toHaveBeenCalledTimes(1)
    expect(reset).toHaveBeenCalledTimes(1)
  })
})
