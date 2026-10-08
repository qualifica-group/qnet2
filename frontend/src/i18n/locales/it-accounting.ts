/**
 * Dominio Contabilità: moduli Gestione Conti, Richieste Proforma, Modalità di Pagamento e Fatture Attive.
 * Raggruppati qui per tenere `it.ts` entro i limiti dimensionali (`engineering.md` §6).
 */

import { financialAccounts } from './it-financial-accounts'
import { invoiceEditor } from './it-invoice-editor'
import { invoiceInstallments } from './it-invoice-installments'
import { invoices } from './it-invoices'
import { paymentMethods } from './it-payment-methods'
import { proformaRequests } from './it-proforma-requests'

export const accounting = {
  financialAccounts,
  proformaRequests,
  paymentMethods,
  invoices,
  invoiceInstallments,
  invoiceEditor,
}
