import { TextSelection, NodeSelection, type Transaction } from '@tiptap/pm/state'
import type { Node as ProseMirrorNode } from '@tiptap/pm/model'

/**
 * Inserts a block node at the selection: it replaces an empty textblock,
 * otherwise it goes right after the block holding the caret. Returns the
 * start position of the inserted node, so the caller can place the selection.
 */
export function insertBlockAtSelection(tr: Transaction, node: ProseMirrorNode): number {
  const { $from } = tr.selection
  const insideTextblock = $from.depth > 0 && $from.parent.isTextblock

  if (insideTextblock && $from.parent.content.size === 0) {
    const start = $from.before()
    tr.replaceWith(start, $from.after(), node)
    return start
  }

  const at = insideTextblock ? $from.after() : tr.selection.to
  tr.insert(at, node)
  return at
}

/** Puts the caret at the start of a just-inserted block, or selects it when it is an atom. */
export function selectInsertedBlock(tr: Transaction, start: number, node: ProseMirrorNode): void {
  if (node.isAtom && !node.isTextblock) {
    tr.setSelection(NodeSelection.create(tr.doc, start))
    return
  }
  tr.setSelection(TextSelection.near(tr.doc.resolve(start + 1)))
}
