import { AxiosError, type AxiosResponse } from 'axios'
import type { PurchaseInstallmentDetail } from '@/features/purchase-invoice-installments/types'

/** Shared test fixtures for the payable installments dialogs. */

export const EDITABLE_FIELD = { visible: true, editable: true, required: false }

export function purchaseDetail(overrides: Partial<PurchaseInstallmentDetail> = {}): PurchaseInstallmentDetail {
  return {
    id: 7,
    sequence: 2,
    due_date: '2026-11-30',
    amount: '400.00',
    payment_method_code: 'MP12',
    paid_amount: null,
    paid_at: null,
    status: 'unpaid',
    residual_amount: '400.00',
    is_overdue: false,
    days_overdue: 0,
    financial_account: null,
    purchase_invoice: {
      id: 3,
      document_type: 'invoice',
      document_number: 'F-12',
      document_date: '2026-10-01',
      total_amount: '800.00',
      supplier: { id: 1, name: 'Fornitore Spa' },
      company: { id: 5, name: 'Qualifica Srl' },
      financial_account: { id: 9, name: 'Conto Intesa' },
      purchase_orders: [{ id: 4, code: '4/2026' }],
      installments: [
        { id: 6, sequence: 1, due_date: '2026-10-30', amount: '400.00', paid_amount: '400.00', status: 'paid' },
        { id: 7, sequence: 2, due_date: '2026-11-30', amount: '400.00', paid_amount: null, status: 'unpaid' },
        { id: 8, sequence: 3, due_date: '2026-12-30', amount: '100.00', paid_amount: null, status: 'unpaid' },
      ],
    },
    field_permissions: { due_date: EDITABLE_FIELD, payment_method_code: EDITABLE_FIELD },
    abilities: { update: true, pay: true },
    ...overrides,
  }
}

export function axiosError(status: number, data: unknown): AxiosError {
  return new AxiosError('failed', String(status), undefined, undefined, { status, data } as AxiosResponse)
}
