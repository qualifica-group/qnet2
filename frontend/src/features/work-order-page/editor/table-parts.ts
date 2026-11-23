import { Node, mergeAttributes } from '@tiptap/core'
import { NODE_NAME } from '@/features/work-order-page/editor/constants'

/**
 * Rows and cells of the page table. The `tableRole` schema field is what
 * `prosemirror-tables` (re-exported by `@tiptap/pm`) uses to recognise them;
 * `Table` copies it onto the schema via `extendNodeSchema`.
 */

declare module '@tiptap/core' {
  // Must repeat the library declaration verbatim to merge into it.
  // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unused-vars
  interface NodeConfig<Options = any, Storage = any> {
    /** `prosemirror-tables` role of the node; copied onto the schema by `Table.extendNodeSchema`. */
    tableRole?: string
  }
}

/** Cell attributes the server profile keeps: only `colspan`/`rowspan` (no widths, no style). */
function cellAttributes() {
  const spanAttribute = (name: 'colspan' | 'rowspan') => ({
    default: 1,
    parseHTML: (element: HTMLElement) => {
      const value = Number(element.getAttribute(name))
      return Number.isInteger(value) && value > 0 ? value : 1
    },
    renderHTML: (attributes: Record<string, unknown>) => (attributes[name] === 1 ? {} : { [name]: attributes[name] }),
  })

  return {
    colspan: spanAttribute('colspan'),
    rowspan: spanAttribute('rowspan'),
    // Required by prosemirror-tables when it splits/merges columns; never serialized.
    colwidth: { default: null, parseHTML: () => null, renderHTML: () => ({}) },
  }
}

export const TableRow = Node.create({
  name: NODE_NAME.tableRow,
  content: '(tableCell | tableHeader)*',
  tableRole: 'row',
  parseHTML() {
    return [{ tag: 'tr' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['tr', mergeAttributes(HTMLAttributes), 0]
  },
})

export const TableHeader = Node.create({
  name: NODE_NAME.tableHeader,
  content: 'block+',
  isolating: true,
  tableRole: 'header_cell',
  addAttributes: cellAttributes,
  parseHTML() {
    return [{ tag: 'th' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['th', mergeAttributes(HTMLAttributes), 0]
  },
})

export const TableCell = Node.create({
  name: NODE_NAME.tableCell,
  content: 'block+',
  isolating: true,
  tableRole: 'cell',
  addAttributes: cellAttributes,
  parseHTML() {
    return [{ tag: 'td' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['td', mergeAttributes(HTMLAttributes), 0]
  },
})
