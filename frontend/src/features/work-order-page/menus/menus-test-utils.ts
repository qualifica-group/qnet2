import { fireEvent, screen } from '@testing-library/react'
import type { Editor } from '@tiptap/react'

// jsdom has no layout: ProseMirror's scroll-into-view reads these.
const emptyRects = () => [] as unknown as DOMRectList
Element.prototype.getClientRects = emptyRects
Range.prototype.getClientRects = emptyRects
Range.prototype.getBoundingClientRect = () => new DOMRect()

/** Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, in jsdom. */
export function openMenu(trigger: HTMLElement): void {
  fireEvent.pointerDown(trigger, { button: 0, ctrlKey: false })
}

export function openMenuByName(name: string | RegExp): void {
  openMenu(screen.getByRole('button', { name }))
}

/** Puts the text selection over the first occurrence of `text` in the document. */
export function selectText(editor: Editor, text: string): void {
  let from = -1
  editor.state.doc.descendants((node, pos) => {
    const index = node.isText ? (node.text?.indexOf(text) ?? -1) : -1
    if (from < 0 && index >= 0) {
      from = pos + index
    }
  })
  editor.commands.setTextSelection({ from, to: from + text.length })
}
