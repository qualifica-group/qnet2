/**
 * Accounting domain: Financial Accounts, Proforma Requests, Payment Methods and Active Invoices modules.
 * Grouped here to keep `en.ts` within the size limits (`engineering.md` §6).
 */

import { financialAccounts } from './en-financial-accounts'
import { invoiceEditor } from './en-invoice-editor'
import { invoices } from './en-invoices'
import { paymentMethods } from './en-payment-methods'
import { proformaRequests } from './en-proforma-requests'

export const accounting = {
  financialAccounts,
  proformaRequests,
  paymentMethods,
  invoices,
  invoiceEditor,
}
