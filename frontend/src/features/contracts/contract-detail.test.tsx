import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { formatDate } from '@/lib/formatting/date-display'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/**
 * AC-043/044/046/047/048: the Contract detail view shows every required
 * field, gates each domain action on its own permission, renders the product
 * lines read-only via the shared component, mounts the two documents
 * sections with the right upload/delete gating, and shows the "Sospeso"
 * badge with the reactivate action gated on `contracts.reactivate`.
 */

const { canMock } = vi.hoisted(() => ({ canMock: vi.fn<(permission: string) => boolean>() }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// Radix `Tabs.Content` only mounts the active panel, and simulating a real
// pointer/click activation on its `Trigger` is unreliable under jsdom (no
// `@testing-library/user-event` in this repo). This suite asserts the VIEW'S
// OWN wiring (which props reach which section), not Radix's tab-switching
// mechanics — so every panel is rendered unconditionally here, `Tabs`/
// `TabsList`/`TabsTrigger` stay the real components (the "tab" role queries
// below still exercise them).
vi.mock('@/components/ui/tabs', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui/tabs')>()
  return {
    ...actual,
    TabsContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  }
})

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({
    resource,
    id,
    canUpload,
    canDelete,
  }: {
    resource: string
    id: number
    canUpload: boolean
    canDelete: boolean
  }) => <div>{`documents:${resource}:${id}:upload=${canUpload}:delete=${canDelete}`}</div>,
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: ({ resource, id }: { resource: string; id: number }) => (
    <div>{`activity:${resource}:${id}`}</div>
  ),
}))

const confirmMock = vi.fn<ConfirmFn>()

function contract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return {
    id: 1,
    quote_id: 3,
    quote: {
      id: 3,
      code: 'QUO-0003',
      title: 'Accordo Acme',
      created_at: '2026-01-05T00:00:00Z',
      revenue_net: '1000.00',
      revenue_vat: '220.00',
      revenue_gross: '1220.00',
      cost_net: '400.00',
      margin_net: '600.00',
    },
    registry: { id: 10, name: 'Acme S.p.A.' },
    opportunity: { id: 20, name: 'Fornitura 2026' },
    company: null,
    company_site: null,
    operational_site: null,
    commercial: { id: 30, name: 'Luca Verdi' },
    reporter: { id: 40, name: 'Giulia Neri' },
    supervisor: { id: 50, name: 'Paolo Blu' },
    payment_method: null,
    contract_status_id: 1,
    contract_status: { id: 1, name: 'Da validare', color: 'slate', group: 'open' },
    accepted_at: '2026-01-10',
    validated_at: null,
    renewal_date: null,
    expiry_date: null,
    terminated_at: null,
    termination_reason: null,
    payment_notes: null,
    comments: null,
    validated_by: null,
    terminated_by: null,
    suspended_at: null,
    status_before_suspension: null,
    is_suspended: false,
    alert: null,
    days_to_expiry: null,
    days_to_renewal: null,
    offer_lines: [],
    summary: {
      revenue: { net: '1000.00', vat: '220.00', gross: '1220.00' },
      cost: { net: '400.00', vat: '88.00', gross: '488.00' },
      margin: { net: '600.00' },
    },
    created_at: '2026-01-10T00:00:00Z',
    updated_at: '2026-01-10T00:00:00Z',
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
      fields: {},
      actions: {
        validate: true,
        schedule: true,
        terminate: true,
        reactivate: true,
        change_status: true,
        export: true,
        view_activity: true,
      },
    },
    ...overrides,
  }
}

function renderView(data: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmContext.Provider value={confirmMock}>
          <ContractDetailView contract={data} />
        </ConfirmContext.Provider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `en-contracts.ts`/`it-contracts.ts` are not yet merged into `en.ts`/`it.ts`
  // (MT-09's job): registered here at runtime, mirrors the wave-1
  // `document-layouts` pre-merge test pattern.
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  confirmMock.mockReset()
  confirmMock.mockResolvedValue(true)
})

/**
 * Formats a `date`-only or full-ISO value exactly like `contract-detail-fields.tsx`'s
 * own (module-private) `formatDate` does: `Intl.DateTimeFormat(language, { dateStyle: 'medium' })`.
 * Computed here rather than hardcoded, so the assertion never depends on the
 * test runner's timezone (a date-only ISO string parses as UTC midnight).
 */
function formatExpectedDate(value: string): string {
  return formatDate(value)
}

describe('ContractDetailView — fields (AC-043)', () => {
  it('shows every field the criterion lists: title, quote code, status badge, client, company/site, opportunity, roles, every lifecycle date, payment notes and comments', () => {
    renderView(
      contract({
        company: { id: 60, name: 'Acme Holding S.r.l.' },
        company_site: { id: 61, name: 'Sede di Milano' },
        validated_at: '2026-01-15',
        validated_by: { id: 70, name: 'Elena Conti' },
        renewal_date: '2026-06-01',
        expiry_date: '2026-12-31',
        terminated_at: '2027-01-05',
        terminated_by: { id: 80, name: 'Marco Neri' },
        payment_notes: 'Bonifico a 30 giorni data fattura',
        comments: 'Nota interna di prova',
      }),
    )

    // Title, quote code (D-1: no code of its own), status badge, client.
    expect(screen.getByText('Accordo Acme')).toBeInTheDocument()
    expect(screen.getByText('QUO-0003')).toBeInTheDocument()
    expect(screen.getByText('Da validare')).toBeInTheDocument()
    expect(screen.getByText('Acme S.p.A.')).toBeInTheDocument()

    // Company/site.
    expect(screen.getByText('Acme Holding S.r.l.')).toBeInTheDocument()
    expect(screen.getByText('Sede di Milano')).toBeInTheDocument()

    // Opportunity, commercial, reporter, supervisor.
    expect(screen.getByText('Fornitura 2026')).toBeInTheDocument()
    expect(screen.getByText('Luca Verdi')).toBeInTheDocument()
    expect(screen.getByText('Giulia Neri')).toBeInTheDocument()
    expect(screen.getByText('Paolo Blu')).toBeInTheDocument()

    // Quote date (quote.created_at), acceptance, validation (+ who), renewal,
    // expiry, termination (+ who) — every lifecycle date AC-043 requires.
    expect(screen.getByText(formatExpectedDate('2026-01-05T00:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatExpectedDate('2026-01-10'))).toBeInTheDocument()
    expect(screen.getByText(formatExpectedDate('2026-01-15'))).toBeInTheDocument()
    expect(screen.getByText('Elena Conti', { exact: false })).toBeInTheDocument()
    expect(screen.getByText(formatExpectedDate('2026-06-01'))).toBeInTheDocument()
    expect(screen.getByText(formatExpectedDate('2026-12-31'))).toBeInTheDocument()
    expect(screen.getByText(formatExpectedDate('2027-01-05'))).toBeInTheDocument()
    expect(screen.getByText('Marco Neri', { exact: false })).toBeInTheDocument()

    // Payment notes and comments.
    expect(screen.getByText('Bonifico a 30 giorni data fattura')).toBeInTheDocument()
    expect(screen.getByText('Nota interna di prova')).toBeInTheDocument()
  })
})

/** A validated contract: the "Valida" action landed it on the `closed_won` "Validato" row. */
function validatedContract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return contract({
    validated_at: '2026-01-15',
    contract_status_id: 2,
    contract_status: { id: 2, name: 'Validato', color: 'emerald', group: 'closed_won' },
    ...overrides,
  })
}

/** A terminated contract: "Disdici" stamped it and moved it onto "Disdetto". */
function terminatedContract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return validatedContract({
    terminated_at: '2026-03-01',
    termination_reason: 'Recesso del cliente',
    contract_status_id: 8,
    contract_status: { id: 8, name: 'Disdetto', color: 'red', group: 'closed_lost' },
    ...overrides,
  })
}

describe('ContractDetailView — action gating (AC-044)', () => {
  it('renders the actions the lifecycle admits when every permission is granted', () => {
    renderView(contract())
    expect(screen.getByRole('button', { name: 'Validate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Terminate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit data' })).toBeInTheDocument()
    // Not validated yet: "Schedule" belongs to the validated state only.
    expect(screen.queryByRole('button', { name: 'Schedule contract' })).not.toBeInTheDocument()
  })

  it('omits "Validate contract" without contracts.validate', () => {
    renderView(contract({ permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, validate: false } } }))
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
  })

  it('omits "Schedule contract" without contracts.schedule', () => {
    renderView(
      validatedContract({
        permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, schedule: false } },
      }),
    )
    expect(screen.queryByRole('button', { name: 'Schedule contract' })).not.toBeInTheDocument()
  })

  it('omits "Terminate contract" without contracts.terminate', () => {
    renderView(contract({ permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, terminate: false } } }))
    expect(screen.queryByRole('button', { name: 'Terminate contract' })).not.toBeInTheDocument()
  })

  it('omits "Edit data" without contracts.update', () => {
    renderView(contract({ permissions: { ...contract().permissions, resource: { ...contract().permissions.resource, update: false } } }))
    expect(screen.queryByRole('button', { name: 'Edit data' })).not.toBeInTheDocument()
  })
})

describe('ContractDetailView — lifecycle gating (user directive 2026-08-31)', () => {
  it('offers only "Valida" and "Disdici" while the contract is not validated', () => {
    renderView(contract())
    expect(screen.getByRole('button', { name: 'Validate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Terminate contract' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Schedule contract' })).not.toBeInTheDocument()
  })

  it('replaces "Valida" with a DISABLED "Programma" once the contract is validated', () => {
    renderView(validatedContract())
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Schedule contract' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Terminate contract' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit data' })).toBeInTheDocument()
  })

  it('treats a contract on a closed_won status as validated even without a validated_at stamp', () => {
    renderView(validatedContract({ validated_at: null }))
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Schedule contract' })).toBeDisabled()
  })

  it('leaves only "Riattiva contratto" once the contract is disdetto', () => {
    renderView(terminatedContract())
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Schedule contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Terminate contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit data' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reactivate contract' })).toBeInTheDocument()
    // The navigation links are not lifecycle-gated.
    expect(screen.getByRole('link', { name: 'View quote' })).toBeInTheDocument()
  })

  it('asks for a restart status when reactivating a disdetto contract, instead of the plain confirm', async () => {
    renderView(terminatedContract())

    fireEvent.click(screen.getByRole('button', { name: 'Reactivate contract' }))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('Restart status')).toBeInTheDocument()
    // The suspended path's inline confirm must NOT fire here.
    expect(confirmMock).not.toHaveBeenCalled()
  })

  it('omits "Riattiva contratto" on a disdetto contract without contracts.reactivate', () => {
    renderView(
      terminatedContract({
        permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, reactivate: false } },
      }),
    )
    expect(screen.queryByRole('button', { name: 'Reactivate contract' })).not.toBeInTheDocument()
  })

  it('never offers "Valida" on a suspended contract (the endpoint would 422)', () => {
    renderView(contract({ is_suspended: true, suspended_at: '2026-02-01T10:00:00Z' }))
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reactivate contract' })).toBeInTheDocument()
  })
})

describe('ContractDetailView — products (AC-046)', () => {
  it('renders the read-only product lines, no editable controls, no "add row" affordance', () => {
    renderView(
      contract({
        offer_lines: [
          {
            id: 1,
            product_id: 1,
            product: { id: 1, code: 'P-1', name: 'Consulenza', category: null, business_function: null },
            quantity: '2.00',
            unit_price: '100.00',
            vat_rate_id: null,
            vat_rate: null,
            net_amount: '200.00',
            vat_amount: '0.00',
            total_amount: '200.00',
            sort_order: 0,
          },
        ],
      }),
    )

    expect(screen.getByText('Consulenza')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /add/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('spinbutton')).not.toBeInTheDocument()
  })
})

describe('ContractDetailView — documents (AC-047)', () => {
  it('shows contract documents gated on attachments permissions, and opportunity documents always read-only', () => {
    canMock.mockImplementation((permission) => permission === 'attachments.create')

    renderView(contract())

    expect(screen.getByText('documents:contract:1:upload=true:delete=false')).toBeInTheDocument()
    expect(screen.getByText('documents:opportunity:20:upload=false:delete=false')).toBeInTheDocument()
  })
})

describe('ContractDetailView — suspended (AC-048)', () => {
  it('shows the "Sospeso" badge and gates "Riattiva contratto" on contracts.reactivate', () => {
    renderView(
      contract({
        is_suspended: true,
        suspended_at: '2026-02-01T10:00:00Z',
        status_before_suspension: { id: 3, name: 'Programmato', color: 'blue', group: 'pending' },
      }),
    )
    expect(screen.getByText('Suspended')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reactivate contract' })).toBeInTheDocument()
  })

  it('omits "Riattiva contratto" without contracts.reactivate even when suspended', () => {
    renderView(
      contract({
        is_suspended: true,
        suspended_at: '2026-02-01T10:00:00Z',
        status_before_suspension: { id: 3, name: 'Programmato', color: 'blue', group: 'pending' },
        permissions: { ...contract().permissions, actions: { ...contract().permissions.actions, reactivate: false } },
      }),
    )
    expect(screen.queryByRole('button', { name: 'Reactivate contract' })).not.toBeInTheDocument()
  })

  it('does not show the "Sospeso" badge nor "Riattiva contratto" when not suspended', () => {
    renderView(contract())
    expect(screen.queryByText('Suspended')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reactivate contract' })).not.toBeInTheDocument()
  })
})
