import { describe, expect, it } from 'vitest'
import {
  createInvoiceLayoutStarter,
  createPurchaseOrderLayoutStarter,
  createStarterConfigForModule,
} from '@/features/document-layouts/invoice-layout-starter'
import { documentLayoutConfigSchema } from '@/features/document-layouts/layout-config-schema'
import { COLUMN_KEYS_BY_SOURCE, PRODUCTS_TABLE_SOURCES_BY_MODULE } from '@/features/document-layouts/products-table-sources'
import type { ProductsTableBlock } from '@/features/document-layouts/layout-config'

describe('invoice layout starter', () => {
  it('is a valid config', () => {
    expect(documentLayoutConfigSchema.safeParse(createInvoiceLayoutStarter()).success).toBe(true)
  })

  it('uses only invoice sources and keys allowed for each source', () => {
    const tables = createInvoiceLayoutStarter().body.blocks.filter(
      (block): block is ProductsTableBlock => block.type === 'products_table',
    )
    expect(tables.map((table) => table.source)).toEqual(['invoice_lines', 'installments'])
    for (const table of tables) {
      expect(PRODUCTS_TABLE_SOURCES_BY_MODULE.invoices).toContain(table.source)
      const keys = table.columns.flatMap((col) => col.lines.flatMap((line) => line.keys))
      for (const key of keys) {
        expect(COLUMN_KEYS_BY_SOURCE[table.source]).toContain(key)
      }
    }
  })

  it('keeps quotes layouts empty', () => {
    expect(createStarterConfigForModule('quotes').body.blocks).toEqual([])
  })

  it('builds a valid purchase order starter with only its own source and keys', () => {
    const starter = createPurchaseOrderLayoutStarter()
    expect(documentLayoutConfigSchema.safeParse(starter).success).toBe(true)
    expect(createStarterConfigForModule('purchase_orders')).toEqual(starter)
    const tables = starter.body.blocks.filter((block): block is ProductsTableBlock => block.type === 'products_table')
    expect(tables.map((table) => table.source)).toEqual(['purchase_order_lines'])
    for (const key of tables[0].columns.flatMap((col) => col.lines.flatMap((line) => line.keys))) {
      expect(COLUMN_KEYS_BY_SOURCE.purchase_order_lines).toContain(key)
    }
  })

  it('offers purchase_order_lines only for the purchase_orders module', () => {
    expect(PRODUCTS_TABLE_SOURCES_BY_MODULE.purchase_orders).toEqual(['purchase_order_lines'])
    expect(PRODUCTS_TABLE_SOURCES_BY_MODULE.quotes).not.toContain('purchase_order_lines')
    expect(PRODUCTS_TABLE_SOURCES_BY_MODULE.invoices).not.toContain('purchase_order_lines')
  })
})
