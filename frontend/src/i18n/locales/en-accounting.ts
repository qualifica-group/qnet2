/**
 * Accounting domain: Financial Accounts, Proforma Requests, Payment Methods and Active Invoices modules.
 * Grouped here to keep `en.ts` within the size limits (`engineering.md` §6).
 */

import { agentFiscalProfiles, commissionAccruals, enasarcoParameters } from './en-agent-commissions'
import { commissionSettlementRuns, commissionSettlements } from './en-commission-settlements'
import { accountStatements } from './en-account-statements'
import { treasuryOverview } from './en-treasury-overview'
import { financialAccounts } from './en-financial-accounts'
import { invoiceEditor } from './en-invoice-editor'
import { invoiceInstallments } from './en-invoice-installments'
import { invoices, invoiceTags } from './en-invoices'
import { compensations } from './en-compensations'
import { ledger } from './en-ledger'
import { managementControl } from './en-management-control'
import { paymentMethods } from './en-payment-methods'
import { proformaRequests } from './en-proforma-requests'
import { purchaseInvoiceInstallments } from './en-purchase-invoice-installments'
import { purchaseInvoices } from './en-purchase-invoices'
import { purchaseInvoiceViews } from './en-purchase-invoice-views'
import { writeOff } from './en-write-off'
import { treasuryCollection } from './en-treasury-collection'
import { treasuryMovements } from './en-treasury-movements'
import { treasuryTypes } from './en-treasury-types'

export const accounting = {
  financialAccounts,
  treasuryMovements,
  accountStatements,
  treasuryOverview,
  treasuryCollection,
  writeOff,
  treasuryTypes,
  proformaRequests,
  paymentMethods,
  invoices,
  invoiceTags,
  invoiceInstallments,
  purchaseInvoices,
  purchaseInvoiceViews,
  purchaseInvoiceInstallments,
  agentFiscalProfiles,
  commissionAccruals,
  commissionSettlementRuns,
  commissionSettlements,
  enasarcoParameters,
  invoiceEditor,
  ledger,
  compensations,
  managementControl,
}
