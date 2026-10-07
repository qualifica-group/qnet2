import type { ContractDataLine, WorkOrderContractData } from '@/features/work-order-contract-data/types'

const NO_PAYMENT = { status: null, payment_agreement: null, has_unpaid: false }

/** Consulting line: 2 x 500.00 = 1000.00, a 200.00 commission (Supplier 150.00 + other 50.00). */
export const CONSULTANCY_LINE: ContractDataLine = {
  quote_line_id: 11,
  product: { id: 1, code: 'CON-001', name: 'Consulenza qualita' },
  typology: { id: 1, code: 'consultancy', name: 'Consulenza' },
  is_institution: false,
  quantity: '2.00',
  unit_price: '500.00',
  net_amount: '1000.00',
  supplier_commission: { commission_type: 'PERCENTAGE', value: '15.0000', base_amount: '1000.00', amount: '150.00', is_stale: false },
  commissions_amount: '250.00',
  net_of_commissions: '750.00',
  effective_revenue: '1000.00',
  warnings: [],
  payment: NO_PAYMENT,
}

/** Institution line with a percentage Supplier commission: 10% of 2000.00 = 200.00. */
export const INSTITUTION_LINE: ContractDataLine = {
  quote_line_id: 12,
  product: { id: 2, code: 'ENT-001', name: 'Certificazione ente' },
  typology: { id: 2, code: 'institution', name: 'Ente' },
  is_institution: true,
  quantity: '1.00',
  unit_price: '2000.00',
  net_amount: '2000.00',
  supplier_commission: { commission_type: 'PERCENTAGE', value: '10.0000', base_amount: '2000.00', amount: '200.00', is_stale: false },
  commissions_amount: '200.00',
  net_of_commissions: '1800.00',
  effective_revenue: '200.00',
  warnings: [],
  payment: {
    status: { id: 3, name: 'Pagato', color: 'green', allows_delivery: true },
    payment_agreement: 'Saldo a 30 giorni',
    has_unpaid: true,
  },
}

export const CONTRACT_DATA: WorkOrderContractData = {
  lines: [CONSULTANCY_LINE, INSTITUTION_LINE],
  totals: {
    net_amount: '3000.00',
    typologies: [
      { id: 1, name: 'Consulenza', net_amount: '1000.00', effective_revenue: '1000.00' },
      { id: 2, name: 'Ente', net_amount: '2000.00', effective_revenue: '200.00' },
      { id: 3, name: 'Formazione', net_amount: '0.00', effective_revenue: '0.00' },
    ],
    effective_revenue: '1200.00',
    commissions_amount: '450.00',
    net_of_commissions: '2550.00',
  },
  commissions_visible: true,
}

/** What an actor without commission visibility receives (spec 0201 D-10). */
export const CONTRACT_DATA_HIDDEN_COMMISSIONS: WorkOrderContractData = {
  lines: [CONSULTANCY_LINE, INSTITUTION_LINE].map((line) => ({
    ...line,
    supplier_commission: null,
    commissions_amount: null,
    net_of_commissions: null,
  })),
  totals: { ...CONTRACT_DATA.totals, commissions_amount: null, net_of_commissions: null },
  commissions_visible: false,
}
