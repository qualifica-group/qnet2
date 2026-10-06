/**
 * Which `products_table` sources and column keys apply per layout module
 * (spec 0195 D-6). The backend validator accepts only the sources of the
 * layout's module; the editor mirrors that so users cannot pick an invalid one.
 */

import type { ColumnKey, ProductsTableSource } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutModule } from '@/features/document-layouts/types'

export const PRODUCTS_TABLE_SOURCES_BY_MODULE: Record<DocumentLayoutModule, readonly ProductsTableSource[]> = {
  quotes: ['offer_lines', 'cost_lines'],
  invoices: ['invoice_lines', 'installments'],
}

const QUOTE_LINE_KEYS: readonly ColumnKey[] = [
  'code',
  'name',
  'description',
  'additional_description',
  'quantity',
  'unit_price',
  'vat_rate',
  'net_amount',
  'vat_amount',
  'total_amount',
]

export const COLUMN_KEYS_BY_SOURCE: Record<ProductsTableSource, readonly ColumnKey[]> = {
  offer_lines: QUOTE_LINE_KEYS,
  cost_lines: QUOTE_LINE_KEYS,
  invoice_lines: [
    'code',
    'name',
    'description',
    'quantity',
    'unit_price',
    'vat_rate',
    'net_amount',
    'vat_amount',
    'total_amount',
  ],
  installments: ['sequence', 'due_date', 'amount', 'payment_method_code', 'status'],
}
