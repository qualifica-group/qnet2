import type { Editor } from '@tiptap/react'

/**
 * `onCloseAutoFocus` of the menus anchored to the editor: Radix would give the
 * focus back to the trigger, the writer expects the caret back in the page.
 */
export function refocusEditor(editor: Editor) {
  return (event: Event) => {
    event.preventDefault()
    if (!editor.isDestroyed) {
      editor.commands.focus()
    }
  }
}
