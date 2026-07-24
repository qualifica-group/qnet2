import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompanySiteDetailView } from '@/features/company-sites/company-site-detail'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/**
 * Spec 0020 AC-020: the "Set as default site" action appears only when the
 * site is not already the default, calls `set-default` and notifies the
 * caller so the grid can refresh.
 */

const fetchCompanySiteMock = vi.fn()
const setDefaultCompanySiteMock = vi.fn()

vi.mock('@/features/company-sites/api', () => ({
  fetchCompanySite: (...args: unknown[]) => fetchCompanySiteMock(...args),
  setDefaultCompanySite: (...args: unknown[]) => setDefaultCompanySiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function site(overrides: Partial<CompanySiteDetailWithPermissions> = {}): CompanySiteDetailWithPermissions {
  return {
    id: 1,
    name: 'Sede Nord',
    notes: null,
    is_default: false,
    logo_url: null,
    personal_data: null,
    banks: [],
    company: null,
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { set_default: true },
    },
    ...overrides,
  }
}

function renderDetail(companySite: CompanySiteDetailWithPermissions, onDefaultChange = vi.fn()) {
  fetchCompanySiteMock.mockResolvedValue(companySite)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <CompanySiteDetailView companySiteId={companySite.id} onDefaultChange={onDefaultChange} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCompanySiteMock.mockReset()
  setDefaultCompanySiteMock.mockReset()
})

describe('CompanySiteDetailView — set-default (AC-020)', () => {
  it('shows the action when the site is not the default', async () => {
    renderDetail(site({ is_default: false }))
    await waitFor(() => expect(screen.getByText('Sede Nord')).toBeInTheDocument())
    expect(screen.getByRole('button', { name: /Set as default site/ })).toBeInTheDocument()
  })

  it('hides the action when the site is already the default', async () => {
    renderDetail(site({ is_default: true }))
    await waitFor(() => expect(screen.getByText('Sede Nord')).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: /Set as default site/ })).not.toBeInTheDocument()
    expect(screen.getByText('Default')).toBeInTheDocument()
  })

  it('hides the action when the actor lacks the set_default permission', async () => {
    renderDetail(
      site({
        is_default: false,
        permissions: {
          resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
          fields: {},
          actions: { set_default: false },
        },
      }),
    )
    await waitFor(() => expect(screen.getByText('Sede Nord')).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: /Set as default site/ })).not.toBeInTheDocument()
  })

  it('calls set-default and notifies the caller on click', async () => {
    const onDefaultChange = vi.fn()
    setDefaultCompanySiteMock.mockResolvedValue(site({ is_default: true }))
    renderDetail(site({ is_default: false }), onDefaultChange)

    await waitFor(() => expect(screen.getByText('Sede Nord')).toBeInTheDocument())
    screen.getByRole('button', { name: /Set as default site/ }).click()

    await waitFor(() => expect(setDefaultCompanySiteMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(onDefaultChange).toHaveBeenCalledTimes(1))
  })
})
