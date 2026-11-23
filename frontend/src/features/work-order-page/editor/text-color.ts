import { Mark, mergeAttributes } from '@tiptap/core'
import { MARK_NAME, TEXT_COLORS, type TextColor } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    textColor: {
      /** Applies a palette text color (replaces any previous one). */
      setTextColor: (color: TextColor) => ReturnType
      unsetTextColor: () => ReturnType
    }
  }
}

function isTextColor(value: string | null): value is TextColor {
  return value !== null && (TEXT_COLORS as readonly string[]).includes(value)
}

/** `span[data-color]`: the color is painted by the prose CSS on theme tokens, never as inline style. */
export const TextColorMark = Mark.create({
  name: MARK_NAME.textColor,
  addAttributes() {
    return {
      color: {
        default: TEXT_COLORS[0],
        parseHTML: (element: HTMLElement) => element.getAttribute('data-color'),
        renderHTML: (attributes: { color?: string }) => ({ 'data-color': attributes.color }),
      },
    }
  },
  parseHTML() {
    return [
      {
        tag: 'span[data-color]',
        getAttrs: (element) => (isTextColor((element as HTMLElement).getAttribute('data-color')) ? null : false),
      },
    ]
  },
  renderHTML({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes), 0]
  },
  addCommands() {
    return {
      setTextColor:
        (color) =>
        ({ commands }) =>
          commands.setMark(this.name, { color }),
      unsetTextColor:
        () =>
        ({ commands }) =>
          commands.unsetMark(this.name),
    }
  },
})
