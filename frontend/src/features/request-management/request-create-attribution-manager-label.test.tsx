import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'

/**
 * AC-045 (create form side): with no persisted request yet, the GA2
 * "Operatore" field relabels from the currently active category tab's
 * resolved labels (spec 0080, `useActiveCategoryManagerLabels`) — a fetch
 * skipped entirely without one active (AC-032: keeps today's exact
 * "Operator (GA2)" string).
 */

const fetchCategoryManagerLabelsMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  createRequest: vi.fn(),
  fetchCategoryManagerLabels: (categoryId: number) => fetchCategoryManagerLabelsMock(categoryId),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

// No connected actor: the Operatore/Sede defaults would otherwise seed both
// controls — irrelevant to this suite, mirrors the sibling attribution suites.
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button" aria-label={labels.triggerLabel} />
  ),
}))

const CATEGORY_TAB_STORAGE_KEY = 'request-management.category-tab'

function renderForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestCreateForm onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCategoryManagerLabelsMock.mockReset()
  window.localStorage.removeItem(CATEGORY_TAB_STORAGE_KEY)
})

describe('Create form — Operatore field label (spec 0080)', () => {
  it('AC-032: keeps "Operator (GA2)" with no active category tab, no fetch made', () => {
    renderForm()

    expect(screen.getByRole('button', { name: 'Operator (GA2)' })).toBeInTheDocument()
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it("AC-045: shows the active tab's resolved level-2 label instead", async () => {
    window.localStorage.setItem(CATEGORY_TAB_STORAGE_KEY, '500')
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '2': 'Consultant' })

    renderForm()

    expect(await screen.findByRole('button', { name: 'Consultant' })).toBeInTheDocument()
    expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledWith(500)
  })
})
