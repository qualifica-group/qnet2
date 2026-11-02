import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { DocumentLayoutPreview } from '@/features/document-layouts/editor/preview/document-layout-preview'
import { PAGE_HEIGHT_TWIPS, PAGE_WIDTH_TWIPS, twipsToPx } from '@/features/document-layouts/editor/preview/preview-scale'
import { createDefaultRun, createDefaultTextBlock, createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

/** The sheet's accessible name: i18n is not initialised in tests, so the key itself. */
const SHEET_LABEL = 'documentLayouts.editor.preview.sheet'

const catalog: DocumentLayoutVariablesCatalog = {
  module: 'quotes',
  categories: [
    { key: 'quote', label: 'Quote', variables: [{ variable: '{quote.code}', label: 'Quote code', type: 'string', example: 'QT-1' }] },
  ],
}

function withBodyBlock(config: DocumentLayoutConfig, block: ReturnType<typeof createDefaultTextBlock>): DocumentLayoutConfig {
  return { ...config, body: { blocks: [...config.body.blocks, block] } }
}

/** Spec 0069 AC-127: A4 aspect ratio, twips->px margin scale, variable tokens rendered as readable chips. */
describe('DocumentLayoutPreview (AC-127)', () => {
  it('renders the sheet at the A4 portrait pixel size derived from the single twips->px constant', () => {
    const config = createEmptyDocumentLayoutConfig()
    render(<DocumentLayoutPreview config={config} images={[]} />)

    const sheet = screen.getByRole('img', { name: SHEET_LABEL })
    expect(sheet).toHaveStyle({ width: `${twipsToPx(PAGE_WIDTH_TWIPS)}px`, height: `${twipsToPx(PAGE_HEIGHT_TWIPS)}px` })
  })

  it('swaps width/height for landscape, keeping the same A4 aspect ratio', () => {
    const config = createEmptyDocumentLayoutConfig()
    config.page.orientation = 'landscape'
    render(<DocumentLayoutPreview config={config} images={[]} />)

    const sheet = screen.getByRole('img', { name: SHEET_LABEL })
    expect(sheet).toHaveStyle({ width: `${twipsToPx(PAGE_HEIGHT_TWIPS)}px`, height: `${twipsToPx(PAGE_WIDTH_TWIPS)}px` })
  })

  it('renders a variable token as a chip with the readable label, not raw text', () => {
    let config = createEmptyDocumentLayoutConfig()
    const block = createDefaultTextBlock('b1')
    block.runs = [{ ...createDefaultRun(), text: 'Ref {quote.code} thanks' }]
    config = withBodyBlock(config, block)

    render(<DocumentLayoutPreview config={config} images={[]} variablesCatalog={catalog} />)

    expect(screen.getByText('Quote code')).toBeInTheDocument()
    expect(screen.queryByText('{quote.code}')).not.toBeInTheDocument()
  })

  it('falls back to the raw token when the catalog has no matching label', () => {
    let config = createEmptyDocumentLayoutConfig()
    const block = createDefaultTextBlock('b1')
    block.runs = [{ ...createDefaultRun(), text: 'Ref {unknown.thing}' }]
    config = withBodyBlock(config, block)

    render(<DocumentLayoutPreview config={config} images={[]} />)

    expect(screen.getByText('{unknown.thing}')).toBeInTheDocument()
  })

  it('renders a page-number field run as a "1" placeholder, not raw text (AC-125)', () => {
    let config = createEmptyDocumentLayoutConfig()
    const block = createDefaultTextBlock('footer-1')
    block.runs = [createDefaultRun('page'), { ...createDefaultRun(), text: ' / ' }, createDefaultRun('total_pages')]
    config = { ...config, footer: { blocks: [block] } }

    render(<DocumentLayoutPreview config={config} images={[]} />)

    const placeholders = screen.getAllByText('1')
    expect(placeholders).toHaveLength(2)
    expect(placeholders[0]).toHaveAttribute('aria-label', 'page')
    expect(placeholders[1]).toHaveAttribute('aria-label', 'total_pages')
  })
})

describe('DocumentLayoutPreview pagination', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  function textBlock(id: string, value: string) {
    const block = createDefaultTextBlock(id)
    block.runs = [{ ...createDefaultRun(), text: value }]
    return block
  }

  it('flows the body blocks that do not fit onto further sheets, repeating header and footer', () => {
    // jsdom has no layout: every body block measures 300px in a 500px body area.
    vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockReturnValue(300)
    vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(500)
    const config = createEmptyDocumentLayoutConfig()
    config.header = { blocks: [textBlock('h', 'Letterhead')] }
    config.body = { blocks: [textBlock('b1', 'First'), textBlock('b2', 'Second')] }
    config.footer = { blocks: [textBlock('f', 'Footer')] }

    render(<DocumentLayoutPreview config={config} images={[]} />)

    const sheets = screen.getAllByRole('img', { name: SHEET_LABEL })
    expect(sheets).toHaveLength(2)
    expect(within(sheets[0]).getByText('First')).toBeInTheDocument()
    expect(within(sheets[0]).queryByText('Second')).not.toBeInTheDocument()
    expect(within(sheets[1]).getByText('Second')).toBeInTheDocument()
    expect(screen.getAllByText('Letterhead')).toHaveLength(2)
    expect(screen.getAllByText('Footer')).toHaveLength(2)
  })

  it('starts a new sheet after a page break and numbers each sheet in the page fields', () => {
    const config = createEmptyDocumentLayoutConfig()
    const footer = createDefaultTextBlock('footer-1')
    footer.runs = [createDefaultRun('page'), { ...createDefaultRun(), text: ' / ' }, createDefaultRun('total_pages')]
    config.body = { blocks: [textBlock('b1', 'First'), { id: 'break', type: 'page_break' }, textBlock('b2', 'Second')] }
    config.footer = { blocks: [footer] }

    render(<DocumentLayoutPreview config={config} images={[]} />)

    const sheets = screen.getAllByRole('img', { name: SHEET_LABEL })
    expect(sheets).toHaveLength(2)
    expect(within(sheets[1]).getByText('Second')).toBeInTheDocument()
    expect(within(sheets[0]).getByLabelText('page')).toHaveTextContent('1')
    expect(within(sheets[1]).getByLabelText('page')).toHaveTextContent('2')
    expect(within(sheets[1]).getByLabelText('total_pages')).toHaveTextContent('2')
  })
})
