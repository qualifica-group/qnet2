import { NodeSelection, type EditorState } from '@tiptap/pm/state'
import type { Node as ProseMirrorNode } from '@tiptap/pm/model'

export interface BlockTarget {
  /** Start position of the block node. */
  pos: number
  node: ProseMirrorNode
}

/**
 * The block a block command acts on: the node at `pos` when given (drag
 * handle / block menu), otherwise the top-level block holding the selection.
 */
export function resolveBlock(state: EditorState, pos?: number): BlockTarget | null {
  const { doc, selection } = state

  if (pos !== undefined) {
    const node = doc.nodeAt(pos)
    return node ? { pos, node } : null
  }
  if (selection instanceof NodeSelection && selection.$from.depth === 0) {
    return { pos: selection.from, node: selection.node }
  }
  if (selection.$from.depth < 1) {
    return null
  }

  const start = selection.$from.before(1)
  const node = doc.nodeAt(start)
  return node ? { pos: start, node } : null
}
