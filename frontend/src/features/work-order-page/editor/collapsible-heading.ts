import { Extension } from '@tiptap/core'
import { NODE_NAME } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    collapsibleHeading: {
      /** Turns the heading at the selection into a collapsible one (or back). */
      toggleHeadingCollapsible: () => ReturnType
    }
  }
}

/**
 * Adds the optional `data-collapsible="true"` attribute to the StarterKit
 * headings (levels 1-4 are configured in `createPageExtensions`). The
 * open/closed state is not an attribute: it lives in `CollapseStateExtension`
 * (D-8).
 */
export const CollapsibleHeading = Extension.create({
  name: 'collapsibleHeading',
  addGlobalAttributes() {
    return [
      {
        types: [NODE_NAME.heading],
        attributes: {
          collapsible: {
            default: false,
            parseHTML: (element: HTMLElement) => element.getAttribute('data-collapsible') === 'true',
            renderHTML: (attributes: { collapsible?: boolean }) =>
              attributes.collapsible ? { 'data-collapsible': 'true' } : {},
          },
        },
      },
    ]
  },
  addCommands() {
    return {
      toggleHeadingCollapsible:
        () =>
        ({ commands, editor }) =>
          commands.updateAttributes(NODE_NAME.heading, {
            collapsible: editor.getAttributes(NODE_NAME.heading).collapsible !== true,
          }),
    }
  },
})
