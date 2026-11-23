import { Mark, mergeAttributes } from '@tiptap/core'
import { HIGHLIGHT_COLORS, MARK_NAME, type HighlightColor } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    highlight: {
      /** Applies the highlighter mark with a palette color (replaces any previous one). */
      setHighlight: (color: HighlightColor) => ReturnType
      unsetHighlight: () => ReturnType
    }
  }
}

function isHighlightColor(value: string | null): value is HighlightColor {
  return value !== null && (HIGHLIGHT_COLORS as readonly string[]).includes(value)
}

/** `mark[data-color]`: the color is painted by the prose CSS on theme tokens, never as inline style. */
export const Highlight = Mark.create({
  name: MARK_NAME.highlight,
  addAttributes() {
    return {
      color: {
        default: HIGHLIGHT_COLORS[0],
        parseHTML: (element: HTMLElement) => element.getAttribute('data-color'),
        renderHTML: (attributes: { color?: string }) => ({ 'data-color': attributes.color }),
      },
    }
  },
  parseHTML() {
    return [
      {
        tag: 'mark[data-color]',
        getAttrs: (element) => (isHighlightColor((element as HTMLElement).getAttribute('data-color')) ? null : false),
      },
    ]
  },
  renderHTML({ HTMLAttributes }) {
    return ['mark', mergeAttributes(HTMLAttributes), 0]
  },
  addCommands() {
    return {
      setHighlight:
        (color) =>
        ({ commands }) =>
          commands.setMark(this.name, { color }),
      unsetHighlight:
        () =>
        ({ commands }) =>
          commands.unsetMark(this.name),
    }
  },
})
