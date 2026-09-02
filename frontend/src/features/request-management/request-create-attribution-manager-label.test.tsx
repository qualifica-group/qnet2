import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateForm } from '@/features/request-management/request-create-form'

/**
 * Spec 0080 AC-045 (create form side), rebound by spec 0097 AC-002: with no
 * persisted request yet, the TEAM slots relabel from the currently active
 * category tab's resolved labels (`useActiveCategoryManagerLabels`) — a fetch
 * skipped entirely without one active, leaving the editor's own default
 * "Account manager n" denominations.
 */

const fetchCategoryManagerLabelsMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  // The create form resolves its "Informazioni aggiuntive" from the picked
  // categories (user directive 2026-08-07): stubbed empty, this suite is not
  // about that block.
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
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

describe('Create form — team slot labels (spec 0080/0097)', () => {
  it('keeps the default denominations with no active category tab, no fetch made', () => {
    renderForm()

    expect(screen.getByRole('button', { name: 'Account manager 2' })).toBeInTheDocument()
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  /** AC-002: the create form shows the same whole-team editor as the work panel. */
  it('renders one trigger per G.A. slot', () => {
    renderForm()

    expect(screen.getByRole('button', { name: 'Account manager 1' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 3' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Account manager 4' })).toBeInTheDocument()
  })

  it("shows the active tab's resolved labels instead, per position", async () => {
    window.localStorage.setItem(CATEGORY_TAB_STORAGE_KEY, '500')
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Senior consultant', '2': 'Consultant' })

    renderForm()

    expect(await screen.findByRole('button', { name: 'Consultant' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Senior consultant' })).toBeInTheDocument()
    expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledWith(500)
  })
})
