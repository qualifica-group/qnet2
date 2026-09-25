import type { ComponentProps } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RuleBuilderDialog } from '@/features/table/custom-filters/rule-builder-dialog'
import type { TableColumn, TableFilterView } from '@/features/table/types'

const createFilterView = vi.fn()
const updateFilterView = vi.fn()

vi.mock('@/features/table/filter-views-api', () => ({
  listFilterViews: vi.fn().mockResolvedValue([]),
  createFilterView: (...args: unknown[]) => createFilterView(...args),
  updateFilterView: (...args: unknown[]) => updateFilterView(...args),
  deleteFilterView: vi.fn(),
  favoriteFilterView: vi.fn(),
  unfavoriteFilterView: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const COLUMNS: TableColumn[] = [
  {
    id: 'name',
    label: 'users.columns.name',
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    filterType: 'text',
  },
]

const CUSTOM_FILTER_VIEW: TableFilterView = {
  id: 9,
  name: 'Open leads',
  filters: {},
  advanced_filters: {},
  visibility: 'private',
  owned: true,
  owner_name: null,
  rules: { and: [{ field: 'name', operator: 'contains', value: 'acme' }], or: [] },
  is_favorite: false,
}

function renderDialog(overrides: Partial<ComponentProps<typeof RuleBuilderDialog>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onApply = vi.fn()
  const onOpenChange = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <RuleBuilderDialog
        domain="users"
        open
        onOpenChange={onOpenChange}
        columns={COLUMNS}
        editingView={null}
        canPublish
        onApply={onApply}
        {...overrides}
      />
    </QueryClientProvider>,
  )
  return { onApply, onOpenChange }
}

/** Picks "Contains" from the row's operator combobox and types a value. */
function fillFirstAndRow(value: string) {
  fireEvent.click(screen.getByRole('combobox', { name: 'Operator' }))
  fireEvent.click(screen.getByRole('option', { name: 'Contains' }))
  fireEvent.change(screen.getByRole('textbox', { name: 'Value' }), { target: { value } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createFilterView.mockReset()
  updateFilterView.mockReset()
})

describe('RuleBuilderDialog', () => {
  it('preselects the first usable column on a default AND row', () => {
    renderDialog()

    expect(screen.getByRole('combobox', { name: 'Field' })).toHaveTextContent('Name')
  })

  it('shows a row-level error and does not call onApply when the operator is missing', async () => {
    const { onApply } = renderDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Operator not valid for this field.')
    expect(onApply).not.toHaveBeenCalled()
  })

  it('Applica builds the rules from the filled row and closes the dialog', async () => {
    const { onApply, onOpenChange } = renderDialog()

    fillFirstAndRow('acme')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(onApply).toHaveBeenCalledWith(
        { and: [{ field: 'name', operator: 'contains', value: 'acme' }], or: [] },
        { viewId: undefined, name: undefined },
      ),
    )
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('"Salva come vista" reveals name + visibility (canPublish), then creates and applies the view', async () => {
    createFilterView.mockResolvedValue({ ...CUSTOM_FILTER_VIEW, id: 42 })
    const { onApply, onOpenChange } = renderDialog()

    fillFirstAndRow('acme')
    fireEvent.click(screen.getByRole('button', { name: 'Save view' }))

    expect(screen.getByRole('group', { name: 'Visibility' })).toBeInTheDocument()
    fireEvent.change(screen.getByRole('textbox', { name: 'View name' }), { target: { value: 'My filter' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(createFilterView).toHaveBeenCalledWith('users', {
        name: 'My filter',
        filters: {},
        advancedFilters: {},
        visibility: 'private',
        rules: { and: [{ field: 'name', operator: 'contains', value: 'acme' }], or: [] },
      }),
    )
    expect(onApply).toHaveBeenCalledWith(
      { and: [{ field: 'name', operator: 'contains', value: 'acme' }], or: [] },
      { viewId: 42, name: CUSTOM_FILTER_VIEW.name },
    )
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('hides the visibility picker without table-filter-views.publish (stays private)', async () => {
    createFilterView.mockResolvedValue(CUSTOM_FILTER_VIEW)
    renderDialog({ canPublish: false })

    fillFirstAndRow('acme')
    fireEvent.click(screen.getByRole('button', { name: 'Save view' }))

    expect(screen.queryByRole('group', { name: 'Visibility' })).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('textbox', { name: 'View name' }), { target: { value: 'My filter' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(createFilterView).toHaveBeenCalledWith(
        'users',
        expect.objectContaining({ visibility: 'private' }),
      ),
    )
  })

  it('pre-fills the form from an editing view with rules, and updates it on save', async () => {
    updateFilterView.mockResolvedValue(CUSTOM_FILTER_VIEW)
    renderDialog({ editingView: CUSTOM_FILTER_VIEW })

    expect(await screen.findByRole('textbox', { name: 'Value' })).toHaveValue('acme')

    fireEvent.click(screen.getByRole('button', { name: 'Save view' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(updateFilterView).toHaveBeenCalledWith('users', 9, {
        name: CUSTOM_FILTER_VIEW.name,
        filters: {},
        advancedFilters: {},
        visibility: 'private',
        rules: CUSTOM_FILTER_VIEW.rules,
      }),
    )
  })
})
