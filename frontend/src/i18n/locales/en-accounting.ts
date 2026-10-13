/**
 * Accounting domain: Financial Accounts, Proforma Requests, Payment Methods and Active Invoices modules.
 * Grouped here to keep `en.ts` within the size limits (`engineering.md` §6).
 */

import { agentFiscalProfiles, commissionAccruals, enasarcoParameters } from './en-agent-commissions'
import { commissionSettlementRuns, commissionSettlements } from './en-commission-settlements'
import { financialAccounts } from './en-financial-accounts'
import { invoiceEditor } from './en-invoice-editor'
import { invoiceInstallments } from './en-invoice-installments'
import { invoices } from './en-invoices'
import { paymentMethods } from './en-payment-methods'
import { proformaRequests } from './en-proforma-requests'
import { purchaseInvoiceInstallments } from './en-purchase-invoice-installments'

export const accounting = {
  financialAccounts,
  proformaRequests,
  paymentMethods,
  invoices,
  invoiceInstallments,
  purchaseInvoiceInstallments,
  agentFiscalProfiles,
  commissionAccruals,
  commissionSettlementRuns,
  commissionSettlements,
  enasarcoParameters,
  invoiceEditor,
}
