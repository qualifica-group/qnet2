import { describe, expect, it } from 'vitest'
import { createInvoiceLayoutStarter, createStarterConfigForModule } from '@/features/document-layouts/invoice-layout-starter'
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
})
