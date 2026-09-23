import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import RegistryDetailPage from '@/pages/registry-detail-page'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0022 AC-A2/AC-A4 — the dedicated registry detail page: fetches the fresh
 * detail for the `:id` param, renders the (separately covered) presentational
 * view, and never shows a blank page on a failed/forbidden fetch. The record
 * card now owns the single "Edit" affordance (`detailOwnsEditAction`
 * convention, Opportunità, user directive 2026-09-22): the page's own job is
 * only to hand it a navigate-to-edit callback, so the mocked
 * `RegistryDetailView` below stands in for that callback the same way
 * `ModuleDetailPage`'s tests do. Gating the button on the response's
 * `permissions` block is `RegistryDetailView`'s own concern, covered in
 * `registry-detail.test.tsx`. The detail view, the page chrome and the HTTP
 * layer are stubbed: what is under test is the page wiring.
 */
const fetchRegistryMock = vi.fn<(id: number) => Promise<RegistryDetailWithPermissions>>()

vi.mock('@/features/registries/api', () => ({
  fetchRegistry: (id: number) => fetchRegistryMock(id),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('@/features/registries/registry-detail', () => ({
  RegistryDetailView: ({
    registry,
    onEdit,
  }: {
    registry: RegistryDetailWithPermissions
    onEdit?: () => void
  }) => (
    <div>
      <h2>{registry.name}</h2>
      {onEdit ? <button onClick={onEdit}>Edit</button> : null}
    </div>
  ),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

function LocationProbe() {
  const location = useLocation()
  return <div data-testid="location">{location.pathname}</div>
}

function permissions(canUpdate: boolean): ResourcePermissions {
  return {
    resource: {
      view: true,
      create: false,
      update: canUpdate,
      delete: false,
      export: false,
      import: false,
    },
    fields: {},
    actions: {},
  }
}

function registry(canUpdate: boolean) {
  return {
    id: 12,
    name: 'Acme S.p.A.',
    permissions: permissions(canUpdate),
  } as unknown as RegistryDetailWithPermissions
}

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/registries/:id" element={<RegistryDetailPage />} />
        </Routes>
        <LocationProbe />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRegistryMock.mockReset()
})

describe('RegistryDetailPage', () => {
  it('fetches the registry of the :id param and renders its detail', async () => {
    fetchRegistryMock.mockResolvedValue(registry(true))

    renderAt('/registries/12')

    expect(await screen.findByRole('heading', { name: 'Acme S.p.A.' })).toBeInTheDocument()
    expect(fetchRegistryMock).toHaveBeenCalledWith(12)
  })

  it('passes a navigate-to-edit callback to the record view, which owns the Edit affordance', async () => {
    fetchRegistryMock.mockResolvedValue(registry(true))

    renderAt('/registries/12')

    const edit = await screen.findByRole('button', { name: 'Edit' })
    fireEvent.click(edit)
    expect(screen.getByTestId('location')).toHaveTextContent('/registries/12/edit')
  })

  it('shows the error state (never a blank page) when the fetch fails', async () => {
    fetchRegistryMock.mockRejectedValue(new Error('403'))

    renderAt('/registries/12')

    expect(
      await screen.findByText('Unable to load the registry. Please try again.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('renders the not-found page for a non-numeric id, without fetching', async () => {
    renderAt('/registries/abc')

    expect(await screen.findByText('Page not found')).toBeInTheDocument()
    expect(fetchRegistryMock).not.toHaveBeenCalled()
  })
})
