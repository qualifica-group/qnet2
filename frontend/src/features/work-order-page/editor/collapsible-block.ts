import { Node, mergeAttributes } from '@tiptap/core'
import { BLOCK_DATA_TYPE, NODE_NAME } from '@/features/work-order-page/editor/constants'
import { insertBlockAtSelection, selectInsertedBlock } from '@/features/work-order-page/editor/insert-block'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    collapsibleBlock: {
      /** Inserts a collapsible block (title + body) with the caret in the title. */
      insertCollapsible: () => ReturnType
    }
  }
}

/** Title line of a collapsible block: `div[data-type="collapsible-summary"]`. */
export const CollapsibleSummary = Node.create({
  name: NODE_NAME.collapsibleSummary,
  content: 'inline*',
  defining: true,
  parseHTML() {
    return [{ tag: `div[data-type="${BLOCK_DATA_TYPE.collapsibleSummary}"]` }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.collapsibleSummary }), 0]
  },
})

/** Body of a collapsible block: `div[data-type="collapsible-content"]`. */
export const CollapsibleContent = Node.create({
  name: NODE_NAME.collapsibleContent,
  content: 'block+',
  defining: true,
  parseHTML() {
    return [{ tag: `div[data-type="${BLOCK_DATA_TYPE.collapsibleContent}"]` }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.collapsibleContent }), 0]
  },
})

/**
 * `div[data-type="collapsible"] > summary + content`. Open/closed is local
 * (`CollapseStateExtension`), the persisted HTML carries no state (D-8).
 */
export const CollapsibleBlock = Node.create({
  name: NODE_NAME.collapsible,
  group: 'block',
  content: `${NODE_NAME.collapsibleSummary} ${NODE_NAME.collapsibleContent}`,
  defining: true,
  isolating: true,
  parseHTML() {
    return [{ tag: `div[data-type="${BLOCK_DATA_TYPE.collapsible}"]` }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.collapsible }), 0]
  },
  addCommands() {
    return {
      insertCollapsible:
        () =>
        ({ tr, dispatch, editor }) => {
          const { collapsible, collapsibleSummary, collapsibleContent, paragraph } = editor.schema.nodes
          const node = collapsible.create(null, [
            collapsibleSummary.create(),
            collapsibleContent.create(null, paragraph.create()),
          ])
          if (dispatch) {
            const start = insertBlockAtSelection(tr, node)
            selectInsertedBlock(tr, start, node)
            tr.scrollIntoView()
          }
          return true
        },
    }
  },
})
