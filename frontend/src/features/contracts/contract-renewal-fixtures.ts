import type { ResourcePermissions } from '@/features/authorization/types'
import type { ContractDetail, ContractDetailWithPermissions, ContractRenewal } from '@/features/contracts/types'

/** Test fixtures of the renewal specs (spec 0358): a programmable, validated contract and its renewals. */

/** The renewal fields of a never-renewed contract, for fixtures that build a full `ContractDetail` by hand. */
export const NEVER_RENEWED: Pick<ContractDetail, 'renewal_number' | 'renewals' | 'initial_period_work_orders_count'> = {
  renewal_number: 0,
  renewals: [],
  initial_period_work_orders_count: 0,
}

export function renewalFixture(overrides: Partial<ContractRenewal> = {}): ContractRenewal {
  return {
    id: 1,
    number: 1,
    starts_on: '2027-01-01',
    renewal_date: '2027-11-30',
    expiry_date: '2027-12-31',
    previous_renewal_date: '2026-11-30',
    previous_expiry_date: '2026-12-31',
    renewed_by: { id: 5, name: 'Ada Alberti' },
    created_at: '2026-12-01T10:00:00Z',
    work_orders_count: 0,
    can_undo: true,
    ...overrides,
  }
}

export function contractFixture(overrides: Partial<ContractDetail> = {}): ContractDetail {
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
    registry: null,
    referent: null,
    opportunity: null,
    company: null,
    company_site: null,
    operational_site: null,
    commercial: null,
    reporter: null,
    supervisor: null,
    payment_method: null,
    source: null,
    product_lines: [],
    rewards: [],
    managers: [],
    manager_labels: {},
    layout: null,
    contract_status_id: 1,
    contract_status: { id: 1, name: 'Validato', color: 'green', group: 'closed_won' },
    accepted_at: '2026-01-01',
    validated_at: '2026-01-10',
    renewal_date: '2026-11-30',
    expiry_date: '2026-12-31',
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
    renewal_number: 0,
    renewals: [],
    initial_period_work_orders_count: 0,
    offer_lines: [],
    summary: {
      revenue: { net: '1000.00', vat: '220.00', gross: '1220.00' },
      cost: { net: '400.00', vat: '88.00', gross: '488.00' },
      margin: { net: '600.00' },
    },
    created_at: '2026-01-10T00:00:00Z',
    updated_at: '2026-01-10T00:00:00Z',
    ...overrides,
  }
}

export function permissionsFixture(actions: Record<string, boolean>): ResourcePermissions {
  return {
    resource: { view: true, create: false, update: true, delete: false, export: false, import: false },
    fields: {},
    actions,
  }
}

export function contractWithPermissions(
  actions: Record<string, boolean>,
  overrides: Partial<ContractDetail> = {},
): ContractDetailWithPermissions {
  return { ...contractFixture(overrides), permissions: permissionsFixture(actions) }
}

/** The full contract detail the `ContractDetailView` suites render (spec 0072 AC-043): a never-validated, open contract. */
export function detailViewContract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
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
    referent: { id: 15, name: 'Mario Bianchi', referent_type: { id: 3, name: 'Amministratore' } },
    opportunity: { id: 20, name: 'Fornitura 2026' },
    company: null,
    company_site: null,
    operational_site: null,
    commercial: { id: 30, name: 'Luca Verdi' },
    reporter: { id: 40, name: 'Giulia Neri' },
    supervisor: { id: 50, name: 'Paolo Blu' },
    payment_method: null,
    source: null,
    product_lines: [],
    rewards: [],
    managers: [],
    manager_labels: {},
    layout: null,
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
    offer_lines: [], ...NEVER_RENEWED,
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
        program: true,
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

/** Chiusura positiva: the "Valida" action landed the contract on the `closed_won` "Validato" row. */
export function validatedContract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return detailViewContract({
    validated_at: '2026-01-15',
    contract_status_id: 2,
    contract_status: { id: 2, name: 'Validato', color: 'emerald', group: 'closed_won' },
    ...overrides,
  })
}

/** Chiusura negativa: "Disdici" stamped the contract and moved it onto "Disdetto". */
export function terminatedContract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return validatedContract({
    terminated_at: '2026-03-01',
    termination_reason: 'Recesso del cliente',
    contract_status_id: 8,
    contract_status: { id: 8, name: 'Disdetto', color: 'red', group: 'closed_lost' },
    ...overrides,
  })
}
