/**
 * Starter config for a new layout of the `invoices` module (spec 0195 D-8):
 * company header, customer block, title, invoice lines with totals,
 * installments, payment details and notes. Pure: every call returns a fresh
 * valid config with fixed block ids. The site logo is not included: layout
 * images are uploaded per layout and there is no logo variable.
 */

import {
  createDefaultProductColumn,
  createDefaultProductColumnLine,
  createDefaultProductsTableBlock,
  createDefaultRun,
  createDefaultTextBlock,
  createEmptyDocumentLayoutConfig,
} from '@/features/document-layouts/layout-config-defaults'
import type {
  ColumnKey,
  DocumentLayoutConfig,
  ProductColumn,
  ProductsTableBlock,
  TextBlock,
} from '@/features/document-layouts/layout-config'
import type { DocumentLayoutModule } from '@/features/document-layouts/types'

const TITLE_FONT_SIZE = 14
const SPACE_SMALL_TWIPS = 120
const SPACE_LARGE_TWIPS = 240

interface TextOptions {
  bold?: boolean
  size?: number
  align?: TextBlock['align']
  spaceBefore?: number
  spaceAfter?: number
}

function textLine(id: string, text: string, options: TextOptions = {}): TextBlock {
  return {
    ...createDefaultTextBlock(id),
    align: options.align ?? 'left',
    space_before: options.spaceBefore ?? 0,
    space_after: options.spaceAfter ?? 0,
    runs: [{ ...createDefaultRun(), text, bold: options.bold ?? false, size: options.size ?? null }],
  }
}

function column(label: string, widthPct: number, align: ProductColumn['align'], lines: ColumnKey[][]): ProductColumn {
  return {
    ...createDefaultProductColumn(),
    label,
    width_pct: widthPct,
    align,
    lines: lines.map((keys) => ({ ...createDefaultProductColumnLine(), keys })),
  }
}

function invoiceLinesTable(): ProductsTableBlock {
  return {
    ...createDefaultProductsTableBlock('invoice-lines'),
    source: 'invoice_lines',
    columns: [
      column('Descrizione', 40, 'left', [['description']]),
      column('Qta', 8, 'right', [['quantity']]),
      column('Prezzo', 12, 'right', [['unit_price']]),
      column('IVA', 8, 'right', [['vat_rate']]),
      column('Imponibile', 14, 'right', [['net_amount']]),
      column('Totale', 18, 'right', [['total_amount']]),
    ],
    totals: {
      show: true,
      rows: [
        { label: 'Imponibile', variable: '{totals.net}', bold: false },
        { label: 'IVA', variable: '{totals.vat}', bold: false },
        { label: 'Totale', variable: '{totals.total}', bold: true },
      ],
    },
  }
}

function installmentsTable(): ProductsTableBlock {
  return {
    ...createDefaultProductsTableBlock('installments'),
    source: 'installments',
    columns: [
      column('N.', 15, 'left', [['sequence']]),
      column('Scadenza', 45, 'left', [['due_date']]),
      column('Importo', 40, 'right', [['amount']]),
    ],
  }
}

/** Fresh starter config for the invoices module. */
export function createInvoiceLayoutStarter(): DocumentLayoutConfig {
  return {
    ...createEmptyDocumentLayoutConfig(),
    header: {
      blocks: [
        textLine('company-name', '{company.name}', { bold: true, size: TITLE_FONT_SIZE }),
        textLine('company-vat', 'P.IVA {company.vat_number}'),
        textLine('company-address', '{company.address}', { spaceAfter: SPACE_SMALL_TWIPS }),
      ],
    },
    body: {
      blocks: [
        textLine('customer-name', '{customer.name}', { bold: true, align: 'right' }),
        textLine('customer-address', '{customer.address}', { align: 'right' }),
        textLine('customer-fiscal', 'P.IVA {customer.vat_number} - CF {customer.tax_code}', { align: 'right' }),
        textLine('customer-electronic', 'SDI {customer.sdi_code} - PEC {customer.pec}', { align: 'right' }),
        textLine('title', '{invoice.type_label} n. {invoice.number_label} del {invoice.document_date}', {
          bold: true,
          size: TITLE_FONT_SIZE,
          spaceBefore: SPACE_LARGE_TWIPS,
          spaceAfter: SPACE_SMALL_TWIPS,
        }),
        invoiceLinesTable(),
        textLine('installments-title', 'Scadenze', {
          bold: true,
          spaceBefore: SPACE_LARGE_TWIPS,
          spaceAfter: SPACE_SMALL_TWIPS,
        }),
        installmentsTable(),
        textLine('payment-method', 'Pagamento: {payment.method_name}', { spaceBefore: SPACE_LARGE_TWIPS }),
        textLine('payment-bank', '{payment.bank_name} {payment.iban}'),
        textLine('payment-instructions', '{payment.payment_instructions}'),
        textLine('notes', '{invoice.notes}', { spaceBefore: SPACE_LARGE_TWIPS }),
      ],
    },
  }
}

/** Initial config for a new layout of `module`: the starter for invoices, empty otherwise. */
export function createStarterConfigForModule(module: DocumentLayoutModule): DocumentLayoutConfig {
  return module === 'invoices' ? createInvoiceLayoutStarter() : createEmptyDocumentLayoutConfig()
}
