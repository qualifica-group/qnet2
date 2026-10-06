import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import RegistryFormPage from '@/pages/registry-form-page'
import type { RegistryDetail } from '@/features/registries/types'

/**
 * Spec 0022 AC-A3 — the dedicated registry create page (`/registries/new`): a
 * successful save navigates to the detail page, cancel goes back to the list.
 * REQUIREMENT CHANGED (spec 0200): there is no `/registries/:id/edit` page any
 * more, the detail edits in place. The guarded form is stubbed: it is covered
 * by its own suites, and what is under test here is the page's wiring.
 */
const fetchRegistryMock = vi.fn()
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/registries/api', () => ({
  fetchRegistry: (id: number) => fetchRegistryMock(id),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const SAVED = { id: 12, name: 'Acme S.p.A.' } as RegistryDetail

vi.mock('@/features/registries/guarded-registry-form', () => ({
  GuardedRegistryForm: ({
    onSuccess,
    onCancel,
  }: {
    onSuccess: (registry: RegistryDetail) => void
    onCancel: () => void
  }) => (
    <div>
      <span>mode:create</span>
      <button type="button" onClick={() => onSuccess(SAVED)}>
        save
      </button>
      <button type="button" onClick={onCancel}>
        cancel
      </button>
    </div>
  ),
}))

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <LocationProbe />
        <Routes>
          <Route path="/registries/new" element={<RegistryFormPage />} />
          <Route path="*" element={null} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRegistryMock.mockReset()
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('RegistryFormPage — create (/registries/new)', () => {
  it('mounts the form in create mode without fetching a detail', async () => {
    renderAt('/registries/new')

    expect(await screen.findByText('mode:create')).toBeInTheDocument()
    expect(fetchRegistryMock).not.toHaveBeenCalled()
  })

  it('navigates to the new registry detail after a successful save', async () => {
    renderAt('/registries/new')

    fireEvent.click(await screen.findByRole('button', { name: 'save' }))

    expect(screen.getByText('location:/registries/12')).toBeInTheDocument()
  })

  it('returns to the list on cancel', async () => {
    renderAt('/registries/new')

    fireEvent.click(await screen.findByRole('button', { name: 'cancel' }))

    expect(screen.getByText('location:/registries')).toBeInTheDocument()
  })

  it('shows the forbidden fallback without registries.create', () => {
    canMock.mockImplementation((permission) => permission !== 'registries.create')

    renderAt('/registries/new')

    expect(screen.getByText("You don't have permission to view registries.")).toBeInTheDocument()
    expect(screen.queryByText('mode:create')).not.toBeInTheDocument()
  })
})
