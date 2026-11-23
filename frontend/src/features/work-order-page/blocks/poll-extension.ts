import { Node, mergeAttributes } from '@tiptap/core'
import { ReactNodeViewRenderer } from '@tiptap/react'
import { PollView } from '@/features/work-order-page/blocks/poll-view'
import { BLOCK_DATA_TYPE, NODE_NAME } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    poll: {
      /** Inserts a block citing an already created poll (only its id is persisted). */
      insertPoll: (pollId: number) => ReturnType
    }
  }
}

function parseId(value: string | null): number | null {
  const id = Number(value)
  return Number.isInteger(id) && id > 0 ? id : null
}

/** `div[data-type="poll"][data-poll-id]`: question, options and votes come from the page payload. */
export const PollExtension = Node.create({
  name: NODE_NAME.poll,
  group: 'block',
  atom: true,
  draggable: true,
  addAttributes() {
    return {
      pollId: {
        default: null,
        parseHTML: (element: HTMLElement) => parseId(element.getAttribute('data-poll-id')),
        renderHTML: (attributes: { pollId?: number | null }) =>
          attributes.pollId ? { 'data-poll-id': String(attributes.pollId) } : {},
      },
    }
  },
  parseHTML() {
    return [{ tag: `div[data-type="${BLOCK_DATA_TYPE.poll}"][data-poll-id]` }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.poll })]
  },
  addNodeView() {
    return ReactNodeViewRenderer(PollView)
  },
  addCommands() {
    return {
      insertPoll:
        (pollId) =>
        ({ commands }) =>
          commands.insertContent({ type: this.name, attrs: { pollId } }),
    }
  },
})
