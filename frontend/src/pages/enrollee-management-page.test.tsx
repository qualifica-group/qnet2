import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import EnrolleeManagementPage from '@/pages/enrollee-management-page'

/**
 * Spec 0130 AC-015: `/enrollee-management` gates on this module's OWN
 * `enrollee-management.viewAny` (never `request-management.viewAny`) and, once
 * granted, mounts the SAME `RequestManagementTable` Gestione Richieste uses —
 * proven here by the real component reading `domain` from `useRequestModule()`
 * instead of a stub, unlike the leaner `rewarded-referents-page.test.tsx`
 * pattern, since the point under test IS the module wiring.
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page' as const,
}))

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => vi.fn() }
})

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void; clearSelection: () => void }, { domain: string }>(
    function TableViewStub({ domain }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {}, clearSelection: () => {} }))
      return <div role="region" aria-label={`table-${domain}`} />
    },
  ),
}))

vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: vi.fn(),
  updateRequestWork: vi.fn(),
  deleteRequest: vi.fn(),
  assignRequestOperators: vi.fn(),
  assignRequestManagerGa1: vi.fn(),
  fetchCategoryManagerLabels: vi.fn(),
  transferRequests: vi.fn(),
  fetchRequestManagementCategories: vi.fn().mockResolvedValue([]),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <EnrolleeManagementPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('EnrolleeManagementPage (spec 0130 AC-015)', () => {
  it('shows the forbidden fallback and mounts no table without enrollee-management.viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view Enrollee Management.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-enrollee-management' })).not.toBeInTheDocument()
    expect(canMock).toHaveBeenCalledWith('enrollee-management.viewAny')
    expect(canMock).not.toHaveBeenCalledWith('request-management.viewAny')
  })

  it('mounts the shared table on the enrollee-management domain with enrollee-management.viewAny', () => {
    canMock.mockReturnValue(true)

    renderPage()

    expect(screen.getByRole('region', { name: 'table-enrollee-management' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view Enrollee Management."),
    ).not.toBeInTheDocument()
  })

  it('renders no "New" creation affordance (D-8), even with every permission granted', () => {
    canMock.mockReturnValue(true)

    renderPage()

    expect(screen.queryByRole('button', { name: /new/i })).not.toBeInTheDocument()
  })
})
