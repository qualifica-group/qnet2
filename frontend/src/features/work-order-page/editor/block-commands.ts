import { Extension } from '@tiptap/core'
import { Fragment, type Node as ProseMirrorNode } from '@tiptap/pm/model'
import { NodeSelection, TextSelection, type Transaction } from '@tiptap/pm/state'
import { resolveBlock } from '@/features/work-order-page/editor/block-target'
import { TRANSFORMABLE_NODES, chainBlockType } from '@/features/work-order-page/editor/block-type'
import { NODE_NAME, type BlockTypeId } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    pageBlocks: {
      /** Swaps the block with its previous sibling. `pos` = block start (drag handle), default: block under the selection. */
      moveBlockUp: (pos?: number) => ReturnType
      moveBlockDown: (pos?: number) => ReturnType
      /** Inserts a copy right after the block. Polls are never duplicated (a poll id is cited once). */
      duplicateBlock: (pos?: number) => ReturnType
      deleteBlock: (pos?: number) => ReturnType
      /** "Trasforma in": converts a text/list/quote/callout block to another type. */
      setBlockType: (type: BlockTypeId, pos?: number) => ReturnType
    }
  }
}

function selectBlock(tr: Transaction, start: number, node: ProseMirrorNode): void {
  if (node.isAtom && !node.isTextblock) {
    tr.setSelection(NodeSelection.create(tr.doc, start))
  } else {
    tr.setSelection(TextSelection.near(tr.doc.resolve(start + 1)))
  }
}

/** Moves the block one step among its siblings; refuses a swap that would break the parent's content rules. */
function moveBlock(tr: Transaction, pos: number, direction: -1 | 1): boolean {
  const $pos = tr.doc.resolve(pos)
  const parent = $pos.parent
  const index = $pos.index()
  const swapIndex = index + direction

  if (swapIndex < 0 || swapIndex >= parent.childCount) {
    return false
  }

  const children = Array.from({ length: parent.childCount }, (_, i) => parent.child(i))
  ;[children[index], children[swapIndex]] = [children[swapIndex], children[index]]
  if (!parent.type.validContent(Fragment.from(children))) {
    return false
  }

  const node = parent.child(index)
  const sibling = parent.child(swapIndex)
  // After removing the block, the sibling occupies its slot: the block lands on the far side of it.
  const newStart = direction < 0 ? pos - sibling.nodeSize : pos + sibling.nodeSize

  tr.delete(pos, pos + node.nodeSize)
  tr.insert(newStart, node)
  selectBlock(tr, newStart, node)
  return true
}

/** Block commands shared by the drag-handle menu, the "/" menu and the keyboard (Alt+Shift+Arrow). */
export const PageBlockCommands = Extension.create({
  name: 'pageBlockCommands',
  addCommands() {
    return {
      moveBlockUp:
        (pos) =>
        ({ state, tr }) => {
          const block = resolveBlock(state, pos)
          return block !== null && moveBlock(tr, block.pos, -1)
        },
      moveBlockDown:
        (pos) =>
        ({ state, tr }) => {
          const block = resolveBlock(state, pos)
          return block !== null && moveBlock(tr, block.pos, 1)
        },
      duplicateBlock:
        (pos) =>
        ({ state, tr, dispatch }) => {
          const block = resolveBlock(state, pos)
          if (!block || block.node.type.name === NODE_NAME.poll) {
            return false
          }
          if (dispatch) {
            tr.insert(block.pos + block.node.nodeSize, block.node.copy(block.node.content))
          }
          return true
        },
      deleteBlock:
        (pos) =>
        ({ state, tr, dispatch }) => {
          const block = resolveBlock(state, pos)
          if (!block) {
            return false
          }
          if (dispatch) {
            tr.delete(block.pos, block.pos + block.node.nodeSize)
          }
          return true
        },
      setBlockType:
        (type, pos) =>
        ({ state, tr, chain, dispatch }) => {
          const block = resolveBlock(state, pos)
          if (!block || !TRANSFORMABLE_NODES.has(block.node.type.name)) {
            return false
          }
          if (!dispatch) {
            return true
          }
          const from = tr.doc.resolve(block.pos + 1)
          const to = tr.doc.resolve(block.pos + block.node.nodeSize - 1)
          tr.setSelection(TextSelection.between(from, to))
          return chainBlockType(chain().clearNodes(), type).run()
        },
    }
  },
  addKeyboardShortcuts() {
    return {
      'Alt-Shift-ArrowUp': ({ editor }) => editor.commands.moveBlockUp(),
      'Alt-Shift-ArrowDown': ({ editor }) => editor.commands.moveBlockDown(),
    }
  },
})
