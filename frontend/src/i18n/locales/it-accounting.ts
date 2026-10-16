/**
 * Dominio Contabilità: moduli Gestione Conti, Richieste Proforma, Modalità di Pagamento e Fatture Attive.
 * Raggruppati qui per tenere `it.ts` entro i limiti dimensionali (`engineering.md` §6).
 */

import { agentFiscalProfiles, commissionAccruals, enasarcoParameters } from './it-agent-commissions'
import { commissionSettlementRuns, commissionSettlements } from './it-commission-settlements'
import { financialAccounts } from './it-financial-accounts'
import { invoiceEditor } from './it-invoice-editor'
import { invoiceInstallments } from './it-invoice-installments'
import { invoices, invoiceTags } from './it-invoices'
import { ledger } from './it-ledger'
import { managementControl } from './it-management-control'
import { paymentMethods } from './it-payment-methods'
import { proformaRequests } from './it-proforma-requests'
import { purchaseInvoiceInstallments } from './it-purchase-invoice-installments'
import { purchaseInvoices } from './it-purchase-invoices'
import { purchaseInvoiceViews } from './it-purchase-invoice-views'

export const accounting = {
  financialAccounts,
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
  managementControl,
}
