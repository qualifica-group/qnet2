import type { PurchaseInvoice } from '@/features/purchase-invoices/types'

/**
 * A document generated from a commission settlement is locked (spec 0267 D-5):
 * the server answers 409 settlement_locked on update and delete, so the record
 * hides those actions regardless of the permission it received.
 */
export function lockedForSettlement(invoice: PurchaseInvoice): PurchaseInvoice {
  if (invoice.commission_settlement === null || invoice.commission_settlement === undefined) {
    return invoice
  }
  const { permissions } = invoice
  return {
    ...invoice,
    permissions: {
      ...permissions,
      resource: { ...permissions.resource, update: false, delete: false },
      actions: { ...permissions.actions, update: false, delete: false },
    },
  }
}
