import { describe, expect, it } from 'vitest'
import { documentLayoutConfigSchema } from '@/features/document-layouts/layout-config-schema'
import {
  createDefaultDividerBlock,
  createDefaultImageBlock,
  createDefaultPageBreakBlock,
  createDefaultProductsTableBlock,
  createDefaultSpacerBlock,
  createDefaultTableBlock,
  createDefaultTextBlock,
  createEmptyDocumentLayoutConfig,
  FONT_SIZE_MAX,
  MARGIN_TWIPS_MAX,
  MAX_BLOCKS_PER_ZONE,
  MAX_PRODUCT_COLUMNS,
} from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutConfig, ProductColumn } from '@/features/document-layouts/layout-config'

/** A structurally valid, non-trivial config exercising every block type (spec 0069 `config_schema`). */
function validConfig(): DocumentLayoutConfig {
  const config = createEmptyDocumentLayoutConfig()
  config.header.blocks = [
    createDefaultTextBlock('header-title'),
    { ...createDefaultImageBlock('header-image', 1), wrap: 'behind_page', height: 800 },
  ]
  config.body.blocks = [
    createDefaultTextBlock('body-intro'),
    createDefaultTableBlock('body-table'),
    createDefaultProductsTableBlock('body-products'),
    createDefaultDividerBlock('body-divider'),
    createDefaultSpacerBlock('body-spacer'),
    createDefaultPageBreakBlock('body-break'),
  ]
  config.footer.blocks = [createDefaultTextBlock('footer-note')]
  return config
}

function productColumn(overrides: Partial<ProductColumn> = {}): ProductColumn {
  return {
    lines: [{ keys: ['code', 'name'], separator: ' - ', bold: false, italic: false, size: null }],
    label: 'Product',
    width_pct: 50,
    align: 'left',
    ...overrides,
  }
}

describe('documentLayoutConfigSchema (spec 0069, AC-111)', () => {
  it('accepts a structurally valid config exercising every block type', () => {
    expect(documentLayoutConfigSchema.safeParse(validConfig()).success).toBe(true)
  })

  it('accepts a bare config of three empty zones over a default page', () => {
    expect(documentLayoutConfigSchema.safeParse(createEmptyDocumentLayoutConfig()).success).toBe(true)
  })

  it('rejects a config missing version', () => {
    const config = createEmptyDocumentLayoutConfig() as unknown as Record<string, unknown>
    delete config.version
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a version other than 1', () => {
    const config = { ...createEmptyDocumentLayoutConfig(), version: 2 }
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects an unknown zone alongside the three real ones', () => {
    const config = { ...createEmptyDocumentLayoutConfig(), sidebar: { blocks: [] } }
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a block with a type outside the seven allowed', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ id: 'x', type: 'video' } as never]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects an unknown key inside a block', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultTextBlock('t1'), extraneous: true } as never]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects an orientation outside the enum', () => {
    const config = createEmptyDocumentLayoutConfig()
    ;(config.page as unknown as Record<string, unknown>).orientation = 'diagonal'
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a margin above the maximum', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.margins.top = MARGIN_TWIPS_MAX + 1
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a negative margin', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.margins.top = -1
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts a margin at exactly the maximum (AC-037, no off-by-one)', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.margins.top = MARGIN_TWIPS_MAX
    config.page.margins.right = MARGIN_TWIPS_MAX
    config.page.margins.bottom = MARGIN_TWIPS_MAX
    config.page.margins.left = MARGIN_TWIPS_MAX
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects a font size outside 6..72', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.default_font.size = 5
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts a font size at exactly 72 (AC-037, no off-by-one)', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.default_font.size = FONT_SIZE_MAX
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects a color that is not 6-digit hex RRGGBB (with #)', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.default_font.color = '#fff'
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a color that is a named CSS color', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.default_font.color = 'red'
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts a valid 6-digit hex color without #', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.default_font.color = 'AB12CD'
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects an align outside the enum', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultTextBlock('t1'), align: 'top' } as never]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a line_height outside 1.0..3.0', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultTextBlock('t1'), line_height: 3.1 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a runs[].field outside page/total_pages', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.footer.blocks = [
      {
        ...createDefaultTextBlock('footer-fields'),
        runs: [
          { text: '', field: 'section' as never, bold: false, italic: false, underline: false, font: null, size: null, color: null },
        ],
      },
    ]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts runs[].field = page and total_pages in the footer', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.footer.blocks = [
      {
        ...createDefaultTextBlock('footer-fields'),
        runs: [
          { text: '', field: 'page', bold: false, italic: false, underline: false, font: null, size: null, color: null },
          { text: '', field: 'total_pages', bold: false, italic: false, underline: false, font: null, size: null, color: null },
        ],
      },
    ]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects more than the max blocks in a single zone', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = Array.from({ length: MAX_BLOCKS_PER_ZONE + 1 }, (_, i) =>
      createDefaultTextBlock(`t${i}`),
    )
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts exactly the max blocks in a single zone (AC-037, no off-by-one)', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = Array.from({ length: MAX_BLOCKS_PER_ZONE }, (_, i) =>
      createDefaultTextBlock(`t${i}`),
    )
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })
})

describe('documentLayoutConfigSchema — image.wrap (spec 0069 D-11, AC-038)', () => {
  it('accepts wrap "behind_page" in the header zone', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.header.blocks = [{ ...createDefaultImageBlock('bg', 1), wrap: 'behind_page', height: 800 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects wrap "behind_page" in the body zone', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultImageBlock('bg', 1), wrap: 'behind_page', height: 800 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects wrap "behind_page" in the footer zone', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.footer.blocks = [{ ...createDefaultImageBlock('bg', 1), wrap: 'behind_page', height: 800 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects wrap "behind_page" with a null height', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.header.blocks = [{ ...createDefaultImageBlock('bg', 1), wrap: 'behind_page', height: null }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects an image side above MAX_IMAGE_POINTS', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.header.blocks = [{ ...createDefaultImageBlock('logo', 1), width: 1201 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a wrap value outside inline/behind_page', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.header.blocks = [{ ...createDefaultImageBlock('logo', 1), wrap: 'floating' as never }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })
})

describe('documentLayoutConfigSchema — table cells (spec 0069, AC-034)', () => {
  it('rejects a table cell containing a non-text block', () => {
    const config = createEmptyDocumentLayoutConfig()
    const table = createDefaultTableBlock('t1')
    table.rows = [
      {
        is_header: false,
        cells: [
          {
            col_span: 1,
            background: null,
            vertical_align: 'top',
            blocks: [createDefaultImageBlock('cell-image', 1) as never],
          },
        ],
      },
    ]
    config.body.blocks = [table]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })
})

describe('documentLayoutConfigSchema — products_table (spec 0069 D-4/D-11, AC-031/AC-039)', () => {
  it('rejects "discount" among a product column line keys', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = createDefaultProductsTableBlock('p1')
    productsTable.columns = [productColumn({ lines: [{ keys: ['discount' as never], separator: '', bold: false, italic: false, size: null }] })]
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts a column stacking two lines (code-name / description, D-11)', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = createDefaultProductsTableBlock('p1')
    productsTable.columns = [
      productColumn({
        lines: [
          { keys: ['code', 'name'], separator: '-', bold: true, italic: false, size: null },
          { keys: ['description'], separator: '', bold: false, italic: true, size: null },
        ],
      }),
    ]
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects a column with an empty lines array', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = createDefaultProductsTableBlock('p1')
    productsTable.columns = [productColumn({ lines: [] })]
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a line with an empty keys array', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = createDefaultProductsTableBlock('p1')
    productsTable.columns = [
      productColumn({ lines: [{ keys: [], separator: '', bold: false, italic: false, size: null }] }),
    ]
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a products_table.source outside offer_lines/cost_lines', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = { ...createDefaultProductsTableBlock('p1'), source: 'archive_lines' as never }
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects more than MAX_PRODUCT_COLUMNS columns', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = createDefaultProductsTableBlock('p1')
    productsTable.columns = Array.from({ length: MAX_PRODUCT_COLUMNS + 1 }, () => productColumn())
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('accepts exactly MAX_PRODUCT_COLUMNS columns (AC-037, no off-by-one)', () => {
    const config = createEmptyDocumentLayoutConfig()
    const productsTable = createDefaultProductsTableBlock('p1')
    productsTable.columns = Array.from({ length: MAX_PRODUCT_COLUMNS }, () => productColumn())
    config.body.blocks = [productsTable]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })
})

describe('documentLayoutConfigSchema — divider (spec 0069 D-11, AC-039b)', () => {
  it('accepts a valid divider', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [createDefaultDividerBlock('d1')]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(true)
  })

  it('rejects width_pct 0', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultDividerBlock('d1'), width_pct: 0 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects width_pct over 100', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultDividerBlock('d1'), width_pct: 101 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects thickness 0', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultDividerBlock('d1'), thickness: 0 }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })

  it('rejects a non-hex color (#000)', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.body.blocks = [{ ...createDefaultDividerBlock('d1'), color: '#000' }]
    expect(documentLayoutConfigSchema.safeParse(config).success).toBe(false)
  })
})
