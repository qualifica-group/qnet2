import { describe, expect, it } from 'vitest'
import { paginateBodyBlocks } from '@/features/document-layouts/editor/preview/preview-pagination'
import { createDefaultTextBlock } from '@/features/document-layouts/layout-config-defaults'
import type { Block } from '@/features/document-layouts/layout-config'

function text(id: string): Block {
  return createDefaultTextBlock(id)
}

function pageBreak(id: string): Block {
  return { id, type: 'page_break' }
}

function ids(pages: Block[][]): string[][] {
  return pages.map((page) => page.map((block) => block.id))
}

describe('paginateBodyBlocks', () => {
  it('keeps every block on one sheet while they fit, gaps included', () => {
    const blocks = [text('a'), text('b'), text('c')]

    expect(ids(paginateBodyBlocks(blocks, { bodyHeight: 320, blockHeights: [100, 100, 100], gap: 10 }))).toEqual([['a', 'b', 'c']])
  })

  it('moves the block that no longer fits to the next sheet', () => {
    const blocks = [text('a'), text('b'), text('c')]

    expect(ids(paginateBodyBlocks(blocks, { bodyHeight: 319, blockHeights: [100, 100, 100], gap: 10 }))).toEqual([['a', 'b'], ['c']])
  })

  it('gives a block taller than a whole sheet a sheet of its own', () => {
    const blocks = [text('a'), text('tall'), text('c')]

    expect(ids(paginateBodyBlocks(blocks, { bodyHeight: 300, blockHeights: [100, 900, 100], gap: 0 }))).toEqual([['a'], ['tall'], ['c']])
  })

  it('starts a new sheet after a page break, but not after a trailing one', () => {
    const blocks = [text('a'), pageBreak('break-1'), text('b'), pageBreak('break-2')]

    expect(ids(paginateBodyBlocks(blocks, null))).toEqual([['a', 'break-1'], ['b', 'break-2']])
  })

  it('keeps one empty sheet for an empty body', () => {
    expect(paginateBodyBlocks([], null)).toEqual([[]])
  })
})
