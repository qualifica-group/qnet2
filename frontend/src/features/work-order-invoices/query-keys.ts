/** Centralized TanStack Query keys of the work order proforma/invoices tabs (spec 0282). */
export const workOrderInvoiceKeys = {
  list: (workOrderId: number) => ['work-orders', 'invoices', workOrderId] as const,
}
