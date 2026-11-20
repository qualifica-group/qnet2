import { NodeSelection } from '@tiptap/pm/state'
import type { Editor } from '@tiptap/react'

/**
 * Starts a native drag of the top-level block at `pos`. ProseMirror's own drop
 * handler does the move: it reads `view.dragging`, deletes the selected block
 * and inserts the slice at the drop position (the dropcursor shows where).
 */
export function startBlockDrag(editor: Editor, pos: number, event: DragEvent): void {
  const { view } = editor
  if (!event.dataTransfer) {
    return
  }
  const selection = NodeSelection.create(view.state.doc, pos)
  view.dispatch(view.state.tr.setSelection(selection))
  const slice = selection.content()
  view.dragging = { slice, move: true }

  event.dataTransfer.effectAllowed = 'copyMove'
  // Firefox does not start a drag without some data.
  event.dataTransfer.setData('text/plain', slice.content.textBetween(0, slice.content.size, '\n'))
  const dom = view.nodeDOM(pos)
  if (dom instanceof HTMLElement) {
    event.dataTransfer.setDragImage(dom, 0, 0)
  }
}

/** ProseMirror clears it on drop; a cancelled drag (Esc) would leave a stale slice behind. */
export function endBlockDrag(editor: Editor): void {
  editor.view.dragging = null
}
