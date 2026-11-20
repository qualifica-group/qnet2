import { Node, mergeAttributes } from '@tiptap/core'
import {
  BLOCK_DATA_TYPE,
  CALLOUT_VARIANTS,
  NODE_NAME,
  type CalloutVariant,
} from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    callout: {
      /** Wraps the selected blocks in a callout, or changes the variant if already inside one. */
      setCallout: (variant?: CalloutVariant) => ReturnType
      unsetCallout: () => ReturnType
    }
  }
}

function isCalloutVariant(value: string | null): value is CalloutVariant {
  return value !== null && (CALLOUT_VARIANTS as readonly string[]).includes(value)
}

/** `div[data-type="callout"][data-variant]` wrapping one or more blocks. */
export const Callout = Node.create({
  name: NODE_NAME.callout,
  group: 'block',
  content: 'block+',
  defining: true,
  addAttributes() {
    return {
      variant: {
        default: CALLOUT_VARIANTS[0],
        parseHTML: (element: HTMLElement) => {
          const variant = element.getAttribute('data-variant')
          return isCalloutVariant(variant) ? variant : CALLOUT_VARIANTS[0]
        },
        renderHTML: (attributes: { variant?: string }) => ({ 'data-variant': attributes.variant }),
      },
    }
  },
  parseHTML() {
    return [{ tag: `div[data-type="${BLOCK_DATA_TYPE.callout}"]` }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.callout }), 0]
  },
  addCommands() {
    return {
      setCallout:
        (variant = CALLOUT_VARIANTS[0]) =>
        ({ commands, editor }) =>
          editor.isActive(this.name)
            ? commands.updateAttributes(this.name, { variant })
            : commands.wrapIn(this.name, { variant }),
      unsetCallout:
        () =>
        ({ commands }) =>
          commands.lift(this.name),
    }
  },
})
