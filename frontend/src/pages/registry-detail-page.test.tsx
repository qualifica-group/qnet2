import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import RegistryDetailPage from '@/pages/registry-detail-page'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0022 AC-A2/AC-A4 — the dedicated registry detail page: fetches the fresh
 * detail for the `:id` param, renders the (separately covered) presentational
 * view, and never shows a blank page on a failed/forbidden fetch. There is no
 * edit page (spec 0200): the view edits in place, so the page hands it no
 * edit callback. The detail view, the page chrome and the HTTP layer are
 * stubbed: what is under test is the page wiring.
 */
const fetchRegistryMock = vi.fn<(id: number) => Promise<RegistryDetailWithPermissions>>()

vi.mock('@/features/registries/api', () => ({
  fetchRegistry: (id: number) => fetchRegistryMock(id),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('@/features/registries/registry-detail', () => ({
  RegistryDetailView: ({ registry }: { registry: RegistryDetailWithPermissions }) => <h2>{registry.name}</h2>,
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

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

  // REQUIREMENT CHANGED (spec 0200): no edit page, so no Edit affordance.
  it('offers no Edit action: the record edits in place', async () => {
    fetchRegistryMock.mockResolvedValue(registry(true))

    renderAt('/registries/12')

    await screen.findByRole('heading', { name: 'Acme S.p.A.' })
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
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
