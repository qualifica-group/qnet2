import type { Editor } from '@tiptap/core'
import { resolveBlock } from '@/features/work-order-page/editor/block-target'
import { NODE_NAME, type BlockTypeId } from '@/features/work-order-page/editor/constants'

/** Node names the "Trasforma in" menu can convert; tables, task/poll blocks etc. are left alone. */
export const TRANSFORMABLE_NODES: ReadonlySet<string> = new Set([
  'paragraph',
  NODE_NAME.heading,
  'bulletList',
  'orderedList',
  'taskList',
  'blockquote',
  'codeBlock',
  NODE_NAME.callout,
])

const LIST_AND_WRAPPER_TYPES: Record<string, BlockTypeId> = {
  paragraph: 'paragraph',
  bulletList: 'bulletList',
  orderedList: 'orderedList',
  taskList: 'taskList',
  blockquote: 'blockquote',
  codeBlock: 'codeBlock',
  [NODE_NAME.callout]: 'callout',
}

/** The "Trasforma in" id describing the block under the selection, or `null` when it is not transformable. */
export function getActiveBlockType(editor: Editor): BlockTypeId | null {
  const block = resolveBlock(editor.state)
  if (!block) {
    return null
  }
  if (block.node.type.name === NODE_NAME.heading) {
    return `heading${block.node.attrs.level as number}` as BlockTypeId
  }
  return LIST_AND_WRAPPER_TYPES[block.node.type.name] ?? null
}

/** Applies `type` to the current selection, assuming it was normalised with `clearNodes()` first. */
export function chainBlockType(chain: ReturnType<Editor['chain']>, type: BlockTypeId) {
  switch (type) {
    case 'paragraph':
      return chain
    case 'heading1':
    case 'heading2':
    case 'heading3':
    case 'heading4':
      return chain.setHeading({ level: Number(type.slice(-1)) as 1 | 2 | 3 | 4 })
    case 'bulletList':
      return chain.toggleBulletList()
    case 'orderedList':
      return chain.toggleOrderedList()
    case 'taskList':
      return chain.toggleTaskList()
    case 'blockquote':
      return chain.setBlockquote()
    case 'codeBlock':
      return chain.setCodeBlock()
    case 'callout':
      return chain.setCallout()
  }
}
