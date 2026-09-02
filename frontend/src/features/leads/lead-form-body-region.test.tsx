import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { LeadForm } from '@/features/leads/lead-form'
import type { LeadDetailWithPermissions } from '@/features/leads/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Directive 2026-07-21 (supersedes spec 0047 D1's read-only Regione): the
 * Regione is a first-class, always-editable, FREE picker — never inherited
 * from the chosen Sede (no auto-fill), and always sent.
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

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `lead-form-body.test.tsx`). Renders as a button calling
 * `onChange(3)` so the free-picker flows are drivable without a real dropdown.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
    disabled,
  }: {
    value: number | null
    onChange: (value: number) => void
    labels: { triggerLabel: string }
    disabled?: boolean
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      data-disabled={disabled ? 'true' : 'false'}
      disabled={disabled}
      onClick={() => onChange(3)}
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
    notes: 'Original note.',
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

describe('LeadFormBody — Regione (free picker, no inheritance, directive 2026-07-21)', () => {
  it('create mode: renders empty and enabled, no Sede chosen yet', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Region')).toBeInTheDocument())
    expect(screen.getByTestId('select-Region')).not.toBeDisabled()
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')
  })

  it('edit mode: pre-fills from the lead and stays editable', async () => {
    render(
      <LeadForm
        mode={{ type: 'edit', lead: lead({ state_id: 3, state: { id: 3, name: 'Lombardy' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Region')).toHaveTextContent('3'))
    expect(screen.getByTestId('select-Region')).not.toBeDisabled()
  })

  it('does NOT inherit the Regione from the picked Sede', async () => {
    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Site')).toBeInTheDocument())
    expect(screen.getByTestId('select-Region')).toHaveTextContent('')

    // Picking a Sede must leave the free Regione untouched (no auto-fill).
    fireEvent.click(screen.getByTestId('select-Site'))

    expect(screen.getByTestId('select-Region')).toHaveTextContent('')
  })

  it('is freely user-editable and sends the picked state_id in the create payload', async () => {
    createLeadMock.mockResolvedValue(lead())

    render(<LeadForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    fireEvent.click(screen.getByTestId('select-Registry'))
    fireEvent.click(screen.getByTestId('select-Campaign'))
    // The user picks the Regione by hand; the mocked select sets it to 3.
    fireEvent.click(screen.getByTestId('select-Region'))
    expect(screen.getByTestId('select-Region')).toHaveTextContent('3')
    fireEvent.click(screen.getByRole('switch', { name: 'Automatically convert to Opportunity' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0].state_id).toBe(3)
  })
})
