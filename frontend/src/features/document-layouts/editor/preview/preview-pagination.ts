import type { Block } from '@/features/document-layouts/layout-config'

/** What the rendered sheets report: the body room of one sheet, each body block's height and the gap between blocks (px). */
export interface PreviewMeasurement {
  bodyHeight: number
  blockHeights: number[]
  gap: number
}

/**
 * Splits the body blocks over A4 sheets the way the generated document
 * flows: a block that no longer fits in the room left starts the next sheet,
 * a `page_break` closes its own. A block taller than a whole sheet still gets
 * a sheet to itself (clipped there: the `.docx` splits it, the preview moves
 * whole blocks only). Without a measurement only the page breaks apply.
 */
export function paginateBodyBlocks(blocks: Block[], measurement: PreviewMeasurement | null): Block[][] {
  const room = measurement?.bodyHeight ?? Number.POSITIVE_INFINITY
  const gap = measurement?.gap ?? 0
  const pages: Block[][] = [[]]
  let used = 0

  blocks.forEach((block, index) => {
    const height = measurement?.blockHeights[index] ?? 0
    const current = pages[pages.length - 1]
    const needed = current.length > 0 ? used + gap + height : height

    if (current.length > 0 && needed > room) {
      pages.push([block])
      used = height
    } else {
      current.push(block)
      used = needed
    }

    if (block.type === 'page_break' && index < blocks.length - 1) {
      pages.push([])
      used = 0
    }
  })

  return pages
}
