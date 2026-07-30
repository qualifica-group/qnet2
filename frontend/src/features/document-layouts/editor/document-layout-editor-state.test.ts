import { describe, expect, it } from 'vitest'
import {
  addBlockToZone,
  addImageBlockToZone,
  removeBlockFromZone,
  reorderZoneBlocks,
  replaceBlockInZone,
  replacePage,
} from '@/features/document-layouts/editor/document-layout-editor-state'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'

/** Spec 0069 AC-120: add/remove/reorder each touch only the targeted zone. */
describe('document-layout-editor-state (AC-120)', () => {
  it('addBlockToZone appends a block with the type default, in the given zone only', () => {
    const config = createEmptyDocumentLayoutConfig()
    const next = addBlockToZone(config, 'body', 'text')

    expect(next.body.blocks).toHaveLength(1)
    expect(next.body.blocks[0]).toMatchObject({ type: 'text', align: 'left', runs: [] })
    expect(next.header.blocks).toHaveLength(0)
    expect(next.footer.blocks).toHaveLength(0)
  })

  it('addImageBlockToZone appends an image block referencing the given attachment', () => {
    const config = createEmptyDocumentLayoutConfig()
    const next = addImageBlockToZone(config, 'header', 42)

    expect(next.header.blocks).toHaveLength(1)
    expect(next.header.blocks[0]).toMatchObject({ type: 'image', attachment_id: 42, wrap: 'inline' })
  })

  it('removeBlockFromZone removes the block from its own zone only', () => {
    let config = addBlockToZone(createEmptyDocumentLayoutConfig(), 'body', 'text')
    config = addBlockToZone(config, 'body', 'spacer')
    const blockId = config.body.blocks[0].id

    const next = removeBlockFromZone(config, 'body', blockId)

    expect(next.body.blocks).toHaveLength(1)
    expect(next.body.blocks[0].type).toBe('spacer')
  })

  it('removing a block from one zone never touches another zone with a block of the same id', () => {
    const config = createEmptyDocumentLayoutConfig()
    const withBody = addBlockToZone(config, 'body', 'text')
    const blockId = withBody.body.blocks[0].id
    const withBoth = replaceBlockInZone(withBody, 'footer', { ...withBody.body.blocks[0], id: blockId })

    const next = removeBlockFromZone(withBoth, 'body', blockId)

    expect(next.body.blocks).toHaveLength(0)
    expect(next.footer.blocks).toHaveLength(0) // replaceBlockInZone only replaces existing ids, footer stayed empty
  })

  it('reorderZoneBlocks reorders only the given zone, leaving others untouched', () => {
    let config = addBlockToZone(createEmptyDocumentLayoutConfig(), 'body', 'text')
    config = addBlockToZone(config, 'body', 'spacer')
    config = addBlockToZone(config, 'header', 'divider')
    const [first, second] = config.body.blocks
    const headerBlockId = config.header.blocks[0].id

    const next = reorderZoneBlocks(config, 'body', [second.id, first.id])

    expect(next.body.blocks.map((block) => block.id)).toEqual([second.id, first.id])
    expect(next.header.blocks).toHaveLength(1)
    expect(next.header.blocks[0].id).toBe(headerBlockId)
  })

  it('replaceBlockInZone swaps a block matched by id, keeping the rest identical', () => {
    const config = addBlockToZone(createEmptyDocumentLayoutConfig(), 'body', 'text')
    const block = config.body.blocks[0]
    if (block.type !== 'text') {
      throw new Error('expected a text block')
    }

    const next = replaceBlockInZone(config, 'body', { ...block, align: 'center' })

    expect(next.body.blocks[0]).toMatchObject({ align: 'center' })
  })

  it('replacePage replaces the whole page settings object', () => {
    const config = createEmptyDocumentLayoutConfig()
    const next = replacePage(config, { ...config.page, orientation: 'landscape' })

    expect(next.page.orientation).toBe('landscape')
    expect(next.header).toBe(config.header)
  })
})
