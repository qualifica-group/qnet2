import { Node, mergeAttributes, type CommandProps } from '@tiptap/core'
import { TextSelection, type Command } from '@tiptap/pm/state'
import type { Node as ProseMirrorNode, Schema } from '@tiptap/pm/model'
import {
  addColumnAfter,
  addColumnBefore,
  addRowAfter,
  addRowBefore,
  deleteColumn,
  deleteRow,
  deleteTable,
  deleteCellSelection,
  goToNextCell,
  tableEditing,
  toggleHeaderRow,
} from '@tiptap/pm/tables'
import {
  NODE_NAME,
  TABLE_DEFAULT_COLS,
  TABLE_DEFAULT_ROWS,
} from '@/features/work-order-page/editor/constants'

export interface InsertTableOptions {
  rows?: number
  cols?: number
  /** First row made of `th` cells (default true, spec 0380 AC-020). */
  withHeaderRow?: boolean
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    pageTable: {
      insertTable: (options?: InsertTableOptions) => ReturnType
      addRowBefore: () => ReturnType
      addRowAfter: () => ReturnType
      deleteRow: () => ReturnType
      addColumnBefore: () => ReturnType
      addColumnAfter: () => ReturnType
      deleteColumn: () => ReturnType
      toggleHeaderRow: () => ReturnType
      deleteTable: () => ReturnType
      goToNextCell: () => ReturnType
      goToPreviousCell: () => ReturnType
    }
  }
}

/** Wraps a `prosemirror-tables` command as a Tiptap command. */
function fromProseMirror(command: Command) {
  return () =>
    ({ state, dispatch }: CommandProps) =>
      command(state, dispatch)
}

function buildTable(schema: Schema, rows: number, cols: number, withHeaderRow: boolean): ProseMirrorNode {
  const { tableRow, tableCell, tableHeader, table, paragraph } = schema.nodes
  const rowNodes: ProseMirrorNode[] = []

  for (let rowIndex = 0; rowIndex < rows; rowIndex += 1) {
    const cellType = withHeaderRow && rowIndex === 0 ? tableHeader : tableCell
    const cells = Array.from({ length: cols }, () => cellType.createAndFill() ?? cellType.create(null, paragraph.create()))
    rowNodes.push(tableRow.create(null, cells))
  }

  return table.create(null, rowNodes)
}

/**
 * Table: `table > tr > th|td` (spec 0380 html_profile). Rows are serialized
 * inside a single `tbody`; `thead`/`tbody`/`tfoot` found while parsing are
 * transparent (ProseMirror ignores unknown wrappers and finds the rows).
 * Operations come from `prosemirror-tables`, already bundled in `@tiptap/pm`.
 */
export const Table = Node.create({
  name: NODE_NAME.table,
  group: 'block',
  content: 'tableRow+',
  isolating: true,
  tableRole: 'table',
  parseHTML() {
    return [{ tag: 'table' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['table', mergeAttributes(HTMLAttributes), ['tbody', 0]]
  },
  extendNodeSchema(extension) {
    return { tableRole: extension.config.tableRole }
  },
  addCommands() {
    return {
      insertTable:
        ({ rows = TABLE_DEFAULT_ROWS, cols = TABLE_DEFAULT_COLS, withHeaderRow = true } = {}) =>
        ({ tr, dispatch, editor }) => {
          if (dispatch) {
            const table = buildTable(editor.schema, rows, cols, withHeaderRow)
            const offset = tr.selection.from + 1
            tr.replaceSelectionWith(table).scrollIntoView()
            tr.setSelection(TextSelection.near(tr.doc.resolve(offset)))
          }
          return true
        },
      addRowBefore: fromProseMirror(addRowBefore),
      addRowAfter: fromProseMirror(addRowAfter),
      deleteRow: fromProseMirror(deleteRow),
      addColumnBefore: fromProseMirror(addColumnBefore),
      addColumnAfter: fromProseMirror(addColumnAfter),
      deleteColumn: fromProseMirror(deleteColumn),
      toggleHeaderRow: fromProseMirror(toggleHeaderRow),
      deleteTable: fromProseMirror(deleteTable),
      goToNextCell: fromProseMirror(goToNextCell(1)),
      goToPreviousCell: fromProseMirror(goToNextCell(-1)),
    }
  },
  addKeyboardShortcuts() {
    return {
      // Tab walks the cells; on the last cell it appends a row (spreadsheet behaviour).
      Tab: ({ editor }) => {
        if (editor.commands.goToNextCell()) {
          return true
        }
        return editor.can().addRowAfter() ? editor.chain().addRowAfter().goToNextCell().run() : false
      },
      'Shift-Tab': ({ editor }) => editor.commands.goToPreviousCell(),
      Backspace: ({ editor }) => deleteCellSelection(editor.state, editor.view.dispatch),
      Delete: ({ editor }) => deleteCellSelection(editor.state, editor.view.dispatch),
    }
  },
  addProseMirrorPlugins() {
    return [tableEditing()]
  },
})
