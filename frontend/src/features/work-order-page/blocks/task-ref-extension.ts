import { Node, mergeAttributes } from '@tiptap/core'
import { ReactNodeViewRenderer } from '@tiptap/react'
import { TaskRefView } from '@/features/work-order-page/blocks/task-ref-view'
import { BLOCK_DATA_TYPE, NODE_NAME } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    taskRef: {
      /** Inserts a block citing an existing task of the work order (only its id is persisted). */
      insertTaskRef: (taskId: number) => ReturnType
    }
  }
}

function parseId(value: string | null): number | null {
  const id = Number(value)
  return Number.isInteger(id) && id > 0 ? id : null
}

/** `div[data-type="task-ref"][data-task-id]`: a real task of the work order, summarised by the node view. */
export const TaskRefExtension = Node.create({
  name: NODE_NAME.taskRef,
  group: 'block',
  atom: true,
  draggable: true,
  addAttributes() {
    return {
      taskId: {
        default: null,
        parseHTML: (element: HTMLElement) => parseId(element.getAttribute('data-task-id')),
        renderHTML: (attributes: { taskId?: number | null }) =>
          attributes.taskId ? { 'data-task-id': String(attributes.taskId) } : {},
      },
    }
  },
  parseHTML() {
    return [{ tag: `div[data-type="${BLOCK_DATA_TYPE.taskRef}"][data-task-id]` }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.taskRef })]
  },
  addNodeView() {
    return ReactNodeViewRenderer(TaskRefView)
  },
  addCommands() {
    return {
      insertTaskRef:
        (taskId) =>
        ({ commands }) =>
          commands.insertContent({ type: this.name, attrs: { taskId } }),
    }
  },
})
