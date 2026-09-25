import type { ComponentProps } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { FilterViewsControl } from '@/features/table/filter-views-control'
import type { TableFilterView } from '@/features/table/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'

const listFilterViews = vi.fn()
const createFilterView = vi.fn()
const deleteFilterView = vi.fn()
const favoriteFilterView = vi.fn()
const unfavoriteFilterView = vi.fn()

vi.mock('@/features/table/filter-views-api', () => ({
  listFilterViews: (...args: unknown[]) => listFilterViews(...args),
  createFilterView: (...args: unknown[]) => createFilterView(...args),
  updateFilterView: vi.fn(),
  deleteFilterView: (...args: unknown[]) => deleteFilterView(...args),
  favoriteFilterView: (...args: unknown[]) => favoriteFilterView(...args),
  unfavoriteFilterView: (...args: unknown[]) => unfavoriteFilterView(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const OWNED_VIEW: TableFilterView = {
  id: 1,
  name: 'My admins',
  filters: { roles: { filterType: 'set', values: ['admin'] } },
  advanced_filters: {},
  visibility: 'private',
  owned: true,
  owner_name: null,
  rules: null,
  is_favorite: false,
}

const SHARED_VIEW: TableFilterView = {
  id: 2,
  name: 'Team overview',
  filters: { status: { filterType: 'set', values: ['active'] } },
  advanced_filters: {},
  visibility: 'shared',
  owned: false,
  owner_name: 'Jane Doe',
  rules: null,
  is_favorite: false,
}

/** A saved view that also captured an advanced filter (spec 0032 AC-009). */
const VIEW_WITH_ADVANCED: TableFilterView = {
  id: 3,
  name: 'Won this quarter',
  filters: {},
  advanced_filters: { status: 'won' },
  visibility: 'private',
  owned: true,
  owner_name: null,
  rules: null,
  is_favorite: false,
}

/** A custom-filter view (spec 0158 D-1). */
const CUSTOM_FILTER_VIEW: TableFilterView = {
  id: 4,
  name: 'Open & mine',
  filters: {},
  advanced_filters: {},
  visibility: 'private',
  owned: true,
  owner_name: null,
  rules: { and: [{ field: 'status', operator: 'equals', value: 'open' }], or: [] },
  is_favorite: false,
}

const CURRENT_FILTERS = { email: { filterType: 'text' } }
const EMPTY_ADVANCED: AdvancedFilterValues = {}

/**
 * Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, so a plain
 * `fireEvent.click` leaves the panel closed in jsdom (see notification-bell).
 */
function openMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: /Saved filters/ }), {
    button: 0,
    ctrlKey: false,
  })
}

function renderControl(
  views: TableFilterView[],
  overrides: Partial<ComponentProps<typeof FilterViewsControl>> = {},
) {
  listFilterViews.mockResolvedValue(views)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onApply = vi.fn()
  const onApplyRules = vi.fn()
  const onNewCustomFilter = vi.fn()
  const onEditCustomFilter = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <ConfirmDialogProvider>
          <FilterViewsControl
            domain="users"
            currentFilters={CURRENT_FILTERS}
            currentAdvancedFilters={EMPTY_ADVANCED}
            onApply={onApply}
            onApplyRules={onApplyRules}
            onNewCustomFilter={onNewCustomFilter}
            onEditCustomFilter={onEditCustomFilter}
            canPublish
            {...overrides}
          />
        </ConfirmDialogProvider>
      </TooltipProvider>
    </QueryClientProvider>,
  )
  return { onApply, onApplyRules, onNewCustomFilter, onEditCustomFilter }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  listFilterViews.mockReset()
  createFilterView.mockReset()
  deleteFilterView.mockReset()
  favoriteFilterView.mockReset()
  unfavoriteFilterView.mockReset()
})

describe('FilterViewsControl', () => {
  it('groups owned and shared-by-others views and applies a view on click', async () => {
    const { onApply } = renderControl([OWNED_VIEW, SHARED_VIEW])

    openMenu()

    expect(await screen.findByRole('menuitem', { name: /My admins/ })).toBeInTheDocument()
    expect(screen.getByText('My views')).toBeInTheDocument()
    expect(screen.getByText(/Shared by Jane Doe/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('menuitem', { name: /My admins/ }))
    expect(onApply).toHaveBeenCalledWith(OWNED_VIEW.filters, OWNED_VIEW.advanced_filters)
  })

  it("applies a view's advanced filters alongside its column filterModel (spec 0032 AC-009)", async () => {
    const { onApply } = renderControl([VIEW_WITH_ADVANCED])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: /Won this quarter/ }))

    expect(onApply).toHaveBeenCalledWith(
      VIEW_WITH_ADVANCED.filters,
      VIEW_WITH_ADVANCED.advanced_filters,
    )
  })

  it('shows the empty state when there are no saved views', async () => {
    renderControl([])

    openMenu()

    expect(await screen.findByText('No saved views yet.')).toBeInTheDocument()
  })

  it('deletes an owned view after confirmation', async () => {
    deleteFilterView.mockResolvedValue(undefined)
    renderControl([OWNED_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Delete view' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(deleteFilterView).toHaveBeenCalledWith('users', 1))
  })

  it('does not delete when the confirmation is dismissed', async () => {
    renderControl([OWNED_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Delete view' }))

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() =>
      expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument(),
    )
    expect(deleteFilterView).not.toHaveBeenCalled()
  })

  it('saves the current filters inline, honoring the chosen visibility', async () => {
    createFilterView.mockResolvedValue({ ...OWNED_VIEW, name: 'Weekly' })
    renderControl([])

    openMenu()

    // Save stays disabled until the view has a name.
    const saveButton = await screen.findByRole('button', { name: 'Save view' })
    expect(saveButton).toBeDisabled()

    fireEvent.change(screen.getByRole('textbox', { name: 'View name' }), {
      target: { value: 'Weekly' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Shared' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save view' }))

    await waitFor(() =>
      expect(createFilterView).toHaveBeenCalledWith('users', {
        name: 'Weekly',
        filters: CURRENT_FILTERS,
        advancedFilters: EMPTY_ADVANCED,
        visibility: 'shared',
      }),
    )
  })

  it('saves the current advanced filters alongside the column filterModel (spec 0032 AC-009)', async () => {
    createFilterView.mockResolvedValue(VIEW_WITH_ADVANCED)
    const currentAdvanced: AdvancedFilterValues = { status: 'won' }
    renderControl([], { currentFilters: {}, currentAdvancedFilters: currentAdvanced })

    openMenu()
    fireEvent.change(screen.getByRole('textbox', { name: 'View name' }), {
      target: { value: 'Won this quarter' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save view' }))

    await waitFor(() =>
      expect(createFilterView).toHaveBeenCalledWith('users', {
        name: 'Won this quarter',
        filters: {},
        advancedFilters: currentAdvanced,
        visibility: 'private',
      }),
    )
  })

  it('offers a hint instead of the form when there are no filters to save', async () => {
    renderControl([], { currentFilters: {}, currentAdvancedFilters: {} })

    openMenu()

    expect(
      await screen.findByText('Apply a filter first to save it as a view.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save view' })).not.toBeInTheDocument()
  })

  it('offers the save form when only an advanced filter is active (no column filterModel)', async () => {
    renderControl([], { currentFilters: {}, currentAdvancedFilters: { status: 'won' } })

    openMenu()

    expect(await screen.findByRole('button', { name: 'Save view' })).toBeInTheDocument()
  })

  it('hides the "Condivisa" visibility option when the actor lacks table-filter-views.publish', async () => {
    renderControl([], { canPublish: false })

    openMenu()

    expect(screen.queryByRole('button', { name: 'Shared' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Private' })).not.toBeInTheDocument()
  })

  it('offers "New custom filter" and calls onNewCustomFilter, closing the menu', async () => {
    const { onNewCustomFilter } = renderControl([])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: /New custom filter/ }))

    expect(onNewCustomFilter).toHaveBeenCalledTimes(1)
  })

  it('applying a view with rules calls onApplyRules instead of onApply', async () => {
    const { onApply, onApplyRules } = renderControl([CUSTOM_FILTER_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: /Open & mine/ }))

    expect(onApplyRules).toHaveBeenCalledWith(CUSTOM_FILTER_VIEW.rules, {
      viewId: CUSTOM_FILTER_VIEW.id,
      name: CUSTOM_FILTER_VIEW.name,
    })
    expect(onApply).not.toHaveBeenCalled()
  })

  it('an owned view with rules offers an edit action calling onEditCustomFilter', async () => {
    const { onEditCustomFilter } = renderControl([CUSTOM_FILTER_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Edit rules' }))

    expect(onEditCustomFilter).toHaveBeenCalledWith(CUSTOM_FILTER_VIEW)
  })

  it('lists favorite views in their own group, ahead of My views/Shared', async () => {
    const favorite = { ...SHARED_VIEW, id: 5, name: 'Pinned', is_favorite: true }
    renderControl([favorite, OWNED_VIEW, SHARED_VIEW])

    openMenu()

    expect(await screen.findByText('Favorites')).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: /Pinned/ })).toBeInTheDocument()
  })

  it('stars a view as favorite, then unstars it', async () => {
    favoriteFilterView.mockResolvedValue({ ...OWNED_VIEW, is_favorite: true })
    renderControl([OWNED_VIEW])

    openMenu()
    fireEvent.click(await screen.findByRole('button', { name: 'Mark as favorite' }))

    await waitFor(() => expect(favoriteFilterView).toHaveBeenCalledWith('users', 1))
  })
})
