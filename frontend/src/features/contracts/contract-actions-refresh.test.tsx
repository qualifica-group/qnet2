import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import { validateContract } from '@/features/contracts/api'
import type { ContractDetailWithPermissions, ContractStatusRef } from '@/features/contracts/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Regression (segnalazione utente 2026-08-31): after an action moved the
 * contract to another status group, the bar kept showing the PREVIOUS
 * group's buttons until the page was reloaded. Cause: the action endpoints
 * answer with `{ data, permissions }` but the api client returned `data`
 * alone, so `permissions.actions` — which the bar ANDs with the lifecycle
 * rule — stayed frozen on the old state.
 */

const { canMock } = vi.hoisted(() => ({ canMock: vi.fn<(permission: string) => boolean>() }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/features/contracts/api', () => ({
  CONTRACTS_DOMAIN: 'contracts',
  CONTRACT_ATTACHABLE_ALIAS: 'contract',
  contractDetailQueryKey: (id: number | null) => ['contracts', 'detail', id],
  fetchContract: vi.fn(),
  updateContract: vi.fn(),
  validateContract: vi.fn(),
  changeContractStatus: vi.fn(),
  terminateContract: vi.fn(),
  reactivateContract: vi.fn(),
  fetchContractProgrammableLines: vi.fn(),
  createContractWorkOrder: vi.fn(),
}))

// The Commesse tab mounts the real `TableView`; stubbed the same way every
// other detail-composing suite stubs it (e.g. `opportunity-quotes-section.test.tsx`)
// so this suite exercises the actions bar refresh, not the generic grid.
vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void; clearSelection: () => void }, { onRowCountChanged?: (count: number | null) => void }>(
    function TableViewStub(_props, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn(), clearSelection: vi.fn() }))
      return <div>work-orders-table-stub</div>
    },
  ),
}))

// The status picker mounts inside the dialog; stubbed to a plain trigger so
// this suite exercises the refresh, not the async for-select network path.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button">{labels.triggerLabel}</button>
  ),
}))

const OPEN_STATUS: ContractStatusRef = { id: 1, name: 'Da validare', color: 'amber', group: 'open' }
const WON_STATUS: ContractStatusRef = { id: 2, name: 'Validato', color: 'emerald', group: 'closed_won' }

function permissions(actions: Partial<ResourcePermissions['actions']>): ResourcePermissions {
  return {
    resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
    fields: {},
    actions: {
      validate: false,
      program: false,
      terminate: false,
      reactivate: false,
      change_status: false,
      export: true,
      view_activity: true,
      ...actions,
    },
  }
}

function contract(status: ContractStatusRef, actions: Partial<ResourcePermissions['actions']>): ContractDetailWithPermissions {
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
    opportunity: null,
    company: null,
    company_site: null,
    operational_site: null,
    commercial: null,
    reporter: null,
    supervisor: null,
    payment_method: null,
    contract_status_id: status.id,
    contract_status: status,
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
    permissions: permissions(actions),
  }
}

function renderView(data: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmContext.Provider value={vi.fn<ConfirmFn>()}>
          <ContractDetailView contract={data} />
        </ConfirmContext.Provider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  vi.mocked(validateContract).mockReset()
})

describe('ContractActionsBar — refresh delle azioni dopo un cambio di stato', () => {
  it('sostituisce i bottoni del gruppo precedente senza ricaricare la pagina', async () => {
    vi.mocked(validateContract).mockResolvedValue({
      ...contract(WON_STATUS, { program: true, terminate: true }),
      validated_at: '2026-08-31',
    })

    renderView(contract(OPEN_STATUS, { validate: true, terminate: true, change_status: true }))

    expect(screen.getByRole('button', { name: 'Validate contract' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Program' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Validate contract' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Validate' }))

    // Nuovo gruppo (chiuso positivo): "Programma" compare attivo,
    // "Valida"/"Modifica stato"/"Modifica dati" spariscono — subito, senza reload.
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Program' })).toBeEnabled()
    })
    expect(screen.queryByRole('button', { name: 'Validate contract' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Change status' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit data' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Terminate contract' })).toBeInTheDocument()
    // Il badge di stato nell'hero segue lo stesso aggiornamento.
    expect(screen.getByText('Validato')).toBeInTheDocument()
  })
})
