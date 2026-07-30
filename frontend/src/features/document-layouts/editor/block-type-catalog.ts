import { Image, Minus, MoveVertical, Scissors, Table, Table2, Type, type LucideIcon } from 'lucide-react'
import type { TFunction } from 'i18next'
import type { Block, BlockType } from '@/features/document-layouts/layout-config'

/** One icon per block type (add-block menu, canvas rows) — a single, centralized mapping (engineering.md §6). */
export const BLOCK_TYPE_ICONS: Record<BlockType, LucideIcon> = {
  text: Type,
  image: Image,
  table: Table,
  products_table: Table2,
  page_break: Scissors,
  spacer: MoveVertical,
  divider: Minus,
}

const RUN_TEXT_PREVIEW_LENGTH = 40

function textBlockSummary(block: Extract<Block, { type: 'text' }>, t: TFunction): string {
  const text = block.runs.map((run) => (run.field ? `{${run.field}}` : run.text)).join('')
  if (!text.trim()) {
    return t('documentLayouts.editor.summaries.emptyText')
  }
  return text.length > RUN_TEXT_PREVIEW_LENGTH ? `${text.slice(0, RUN_TEXT_PREVIEW_LENGTH)}…` : text
}

/** One-line, truncated description of a block for the canvas row (AC-120 list readability). */
export function blockSummary(block: Block, t: TFunction): string {
  switch (block.type) {
    case 'text':
      return textBlockSummary(block, t)
    case 'image':
      return t('documentLayouts.editor.summaries.image')
    case 'table':
      return t('documentLayouts.editor.summaries.table', { rows: block.rows.length, columns: block.columns.length })
    case 'products_table':
      return t('documentLayouts.editor.summaries.productsTable', { columns: block.columns.length })
    case 'page_break':
      return t('documentLayouts.editor.summaries.pageBreak')
    case 'spacer':
      return t('documentLayouts.editor.summaries.spacer', { height: block.height })
    case 'divider':
      return t('documentLayouts.editor.summaries.divider')
  }
}
