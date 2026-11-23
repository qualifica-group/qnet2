import { TextSelection } from '@tiptap/pm/state'
import { BubbleMenu } from '@tiptap/react/menus'
import type { Editor } from '@tiptap/react'
import { NODE_NAME } from '@/features/work-order-page/editor/constants'
import { SelectionToolbar } from '@/features/work-order-page/menus/selection-toolbar'

const BUBBLE_OFFSET = 8

/** Task and poll blocks are not formatted text: a selection touching one gets no bar. */
const EXCLUDED_NODES: ReadonlySet<string> = new Set([NODE_NAME.taskRef, NODE_NAME.poll])

interface ShowProps {
  editor: Editor
  element: HTMLElement
  from: number
  to: number
}

function shouldShowSelectionBar({ editor, element, from, to }: ShowProps): boolean {
  // The plugin re-evaluates on a timer that can outlive the editor.
  if (editor.isDestroyed) {
    return false
  }
  const { selection, doc } = editor.state
  if (!editor.isEditable || !(selection instanceof TextSelection) || selection.empty) {
    return false
  }
  // Focus may sit in the bar itself (link input) or in one of its menus.
  if (!editor.view.hasFocus() && !element.contains(document.activeElement)) {
    return false
  }
  let excluded = false
  doc.nodesBetween(from, to, (node) => {
    if (EXCLUDED_NODES.has(node.type.name)) {
      excluded = true
    }
    return !excluded
  })
  return !excluded
}

/** Floating selection bar: Tiptap's `BubbleMenu` (floating-ui) around the shared toolbar. */
export function SelectionBubbleMenu({ editor }: { editor: Editor }) {
  return (
    <BubbleMenu
      editor={editor}
      shouldShow={shouldShowSelectionBar}
      options={{ placement: 'top', offset: BUBBLE_OFFSET }}
    >
      <SelectionToolbar editor={editor} />
    </BubbleMenu>
  )
}
