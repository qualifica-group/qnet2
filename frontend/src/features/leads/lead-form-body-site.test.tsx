import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetailWithPermissions } from '@/features/leads/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { CampaignForSelectItem } from '@/features/campaigns/for-select-api'

/**
 * Project -> campaign -> lead prefill chain: picking a Campaign prefills the
 * Lead's Sede (`operational_site_id`) from the campaign's own
 * `for-select` `meta.operational_site` (`{id, label}`) — a PREFILL, not a
 * lock: the Site field stays fully editable/clearable afterwards.
 * Deliberately does NOT touch `state_id`/Regione: user decision confirms the
 * Regione stays a free, never-inherited field (`lead-form-body-region.test.tsx`);
 * `CampaignForSelectResource.meta.operational_site` carries no Regione data
 * at all, so this lane only ever wires the Sede half of the chain.
 * Split out of `lead-form-body.test.tsx` for size (engineering.md §6).
 */

const createLeadMock = vi.fn()
const updateLeadMock = vi.fn()

vi.mock('@/features/leads/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/leads/api')>('@/features/leads/api')
  return {
    ...actual,
    createLead: (...args: unknown[]) => createLeadMock(...args),
    updateLead: (...args: unknown[]) => updateLeadMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

/** The Campaign's for-select item, carrying its Sede's `meta` (the only field this suite's picker exercises). */
const CAMPAIGN_ITEM: CampaignForSelectItem = {
  id: 20,
  label: 'Spring push',
  meta: { operational_site: { id: 55, label: 'Warehouse A' } },
}

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `lead-form-body.test.tsx`). The Campaign button also invokes
 * `onItemChange` with its `meta`, so the prefill handler under test is
 * exercisable without a real dropdown.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      onClick={() => {
        if (labels.triggerLabel === 'Campaign') {
          onChange(CAMPAIGN_ITEM.id)
          onItemChange?.(CAMPAIGN_ITEM)
          return
        }
        onChange(3)
      }}
    >
      {value ?? ''}
    </button>
  ),
}))

/** Stubs the "Prodotti di interesse" picker: not exercised by this suite. */
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    labels,
    disabled,
  }: {
    value: number[]
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <div data-testid={`multi-${labels.triggerLabel}`} data-disabled={disabled ? 'true' : 'false'}>
      {value.join(',')}
    </div>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function lead(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 9,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'not_associated',
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: null,
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('LeadFormBody — Sede prefill from Campaign (project -> campaign -> lead chain)', () => {
  it('prefills the Site field from the picked Campaign meta, still editable', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Campaign')).toBeInTheDocument())
    expect(screen.getByTestId('select-Site')).toHaveTextContent('')

    fireEvent.click(screen.getByTestId('select-Campaign'))

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent('55'))
    expect(screen.getByTestId('select-Site')).not.toBeDisabled()

    // The prefill is not a lock: the user can still override it manually.
    fireEvent.click(screen.getByTestId('select-Site'))
    expect(screen.getByTestId('select-Site')).toHaveTextContent('3')
  })

  it('sends the campaign-prefilled operational_site_id in the create payload', async () => {
    createLeadMock.mockResolvedValue(lead())

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Registry'))
    fireEvent.click(screen.getByTestId('select-Campaign'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0].operational_site_id).toBe(55)
  })

  it('does NOT touch the Regione (directive 2026-07-21: free, never-inherited field)', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Campaign')).toBeInTheDocument())
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')

    fireEvent.click(screen.getByTestId('select-Campaign'))

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent('55'))
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')
  })

  it('a different single-select field never triggers the Sede prefill', async () => {
    render(
      <LeadForm
        mode={{ type: 'edit', lead: lead({ operational_site_id: 8, operational_site: { id: 8, label: 'Depot B' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Site')).toHaveTextContent('8'))

    fireEvent.click(screen.getByTestId('select-Source'))
    expect(screen.getByTestId('select-Site')).toHaveTextContent('8')
  })
})
