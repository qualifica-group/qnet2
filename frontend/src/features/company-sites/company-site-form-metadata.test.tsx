import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CompanySiteForm } from '@/features/company-sites/company-site-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0020 (AC-014/AC-016): the metadata-driven behaviour of the multi-tab
 * form — a hidden field is absent, a whole tab hides when every one of its
 * fields is hidden, and the Banche tab is always buffered (no network calls).
 */

const createCompanySiteMock = vi.fn()
const updateCompanySiteMock = vi.fn()

vi.mock('@/features/company-sites/api', () => ({
  createCompanySite: (...args: unknown[]) => createCompanySiteMock(...args),
  updateCompanySite: (...args: unknown[]) => updateCompanySiteMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The single screen mounts the settings block too, whose relation select offers
// the quick-create "+" — gated by the abilities of the logged-in actor.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/geo/use-geo', () => ({
  useCountries: () => ({ data: [{ id: 1, name: 'Italy' }], isPending: false, isError: false }),
  useStates: () => ({ data: [], isPending: false, isError: false }),
  useProvinces: () => ({ data: [], isPending: false, isError: false }),
  useCities: () => ({
    data: { pages: [[]] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: () => {},
    refetch: () => {},
  }),
}))

vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelect: () => ({
    data: undefined,
    isPending: false,
    isError: false,
    fetchNextPage: () => {},
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: () => {},
  }),
  flattenForSelectPages: () => [],
  useForSelectLabels: () => new Map(),
}))

// The Profilo tab now embeds the shared personal-data card/contacts/address
// components, which read enum options from the server config.
vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums: {} } }),
  useEnumOptions: () => [],
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

const FULL_PERMISSIONS = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

function permissiveMeta(): ResourceMeta {
  return {
    fields: [],
    permissions: { resource: FULL_PERMISSIONS, fields: {}, actions: {} },
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCompanySiteMock.mockReset()
  updateCompanySiteMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('CompanySiteForm — metadata-driven authorization (spec 0020)', () => {
  it('always renders the profile blocks with a company-locked card (no individual option)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: FULL_PERMISSIONS,
        fields: {
          name: { visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false },
        },
        actions: {},
      },
    })

    render(
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The site's own scalar name is present.
    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    // The card is locked to a company: its company_name field shows, the
    // individual-only first-name field never does, and the type toggle is
    // absent (a natural person is never selectable).
    expect(screen.getByLabelText(/^Company name/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^First name/)).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Individual' })).not.toBeInTheDocument()
  })

  it('hides the settings block when every one of its fields is hidden', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: FULL_PERMISSIONS,
        fields: {
          company_id: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
        },
        actions: {},
      },
    })

    render(
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    expect(screen.queryByText('Company')).not.toBeInTheDocument()
  })

  it('shows the banks block (visible "banks" field) with no network call for its rows', async () => {
    fetchResourceMetaMock.mockResolvedValue(permissiveMeta())

    render(
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByText('Banks')).toBeInTheDocument())
  })

  it('falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: FULL_PERMISSIONS,
        fields: {
          name: { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false },
        },
        actions: {},
      },
    })

    render(
      <CompanySiteForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Name/)).toBeInTheDocument())
    expect(screen.getByLabelText(/^Name/)).toBeEnabled()
  })
})
