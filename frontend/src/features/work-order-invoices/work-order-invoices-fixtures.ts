import type { WorkOrderInvoices } from '@/features/work-order-invoices/types'

export const INVOICES: WorkOrderInvoices = {
  active: [
    {
      id: 11,
      type: 'invoice',
      number_label: '12/2026',
      document_date: '2026-09-30',
      external_number: 'FT-77',
      external_date: '2026-09-30',
      customer: { id: 1, name: 'Acme Spa' },
      company: { id: 1, name: 'Qualifica' },
      net_amount: '1000.00',
      vat_amount: '220.00',
      total_amount: '1220.00',
      collected_amount: '500.00',
      residual_amount: '720.00',
      payment_status: 'overdue',
    },
    {
      id: 10,
      type: 'proforma',
      number_label: '3/2026',
      document_date: '2026-09-01',
      external_number: null,
      external_date: null,
      customer: { id: 1, name: 'Acme Spa' },
      company: { id: 1, name: 'Qualifica' },
      net_amount: '100.00',
      vat_amount: '22.00',
      total_amount: '122.00',
      collected_amount: '0.00',
      residual_amount: '122.00',
      payment_status: 'not_due',
    },
  ],
  passive: [
    {
      id: 21,
      document_type: 'invoice',
      document_number: 'PF-900',
      document_date: '2026-09-15',
      supplier: { id: 5, name: 'Forniture Srl' },
      company: { id: 1, name: 'Qualifica' },
      total_amount: '180.00',
      work_order_amount: '150.00',
      paid_amount: '50.00',
      residual_amount: '130.00',
    },
  ],
  totals: {
    active: { total_amount: '1342.00', collected_amount: '500.00', residual_amount: '842.00' },
    passive: { total_amount: '180.00', work_order_amount: '150.00', paid_amount: '50.00', residual_amount: '130.00' },
  },
}

export const EMPTY_INVOICES: WorkOrderInvoices = {
  active: [],
  passive: [],
  totals: {
    active: { total_amount: '0.00', collected_amount: '0.00', residual_amount: '0.00' },
    passive: { total_amount: '0.00', work_order_amount: '0.00', paid_amount: '0.00', residual_amount: '0.00' },
  },
}
