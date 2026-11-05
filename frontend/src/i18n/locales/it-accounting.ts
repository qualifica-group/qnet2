/**
 * Dominio Contabilità: moduli Gestione Conti, Richieste Proforma, Modalità di Pagamento e Fatture Attive.
 * Raggruppati qui per tenere `it.ts` entro i limiti dimensionali (`engineering.md` §6).
 */

import { agentFiscalProfiles, commissionAccruals, enasarcoParameters } from './it-agent-commissions'
import { commissionSettlementRuns, commissionSettlements } from './it-commission-settlements'
import { accountStatements } from './it-account-statements'
import { treasuryOverview } from './it-treasury-overview'
import { financialAccounts } from './it-financial-accounts'
import { invoiceEditor } from './it-invoice-editor'
import { invoiceInstallments } from './it-invoice-installments'
import { invoices, invoiceTags } from './it-invoices'
import { compensations } from './it-compensations'
import { ledger } from './it-ledger'
import { managementControl } from './it-management-control'
import { paymentMethods } from './it-payment-methods'
import { proformaRequests } from './it-proforma-requests'
import { purchaseInvoiceInstallments } from './it-purchase-invoice-installments'
import { purchaseInvoices } from './it-purchase-invoices'
import { purchaseInvoiceViews } from './it-purchase-invoice-views'
import { writeOff } from './it-write-off'
import { treasuryCollection } from './it-treasury-collection'
import { treasuryMovements } from './it-treasury-movements'
import { treasuryTypes } from './it-treasury-types'

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
