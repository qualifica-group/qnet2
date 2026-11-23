import type { LucideIcon } from 'lucide-react'
import {
  CalendarDays,
  ChartBar,
  ChevronsDownUp,
  Code,
  FileCode,
  Heading1,
  Heading2,
  Heading3,
  Heading4,
  Image,
  Info,
  List,
  ListOrdered,
  ListTodo,
  Minus,
  Pilcrow,
  Quote,
  SquareCheckBig,
  Table,
} from 'lucide-react'
import type { Editor } from '@tiptap/react'
import { resolveBlock } from '@/features/work-order-page/editor/block-target'
import { getActiveBlockType } from '@/features/work-order-page/editor/block-type'
import {
  NODE_NAME,
  TABLE_DEFAULT_COLS,
  TABLE_DEFAULT_ROWS,
  type BlockTypeId,
  type HeadingLevel,
} from '@/features/work-order-page/editor/constants'
import type { ShortcutTokens } from '@/features/work-order-page/menus/shortcut-label'

/** What the menus can trigger outside the editor (dialogs, file picker). */
export interface PageMenuActions {
  insertTask: () => void
  insertPoll: () => void
  pickImage: () => void
}

type CollapsibleHeadingId = `collapsibleHeading${HeadingLevel}`

export type PageMenuItemId =
  | BlockTypeId
  | 'collapsibleParagraph'
  | CollapsibleHeadingId
  | 'inlineCode'
  | 'table'
  | 'divider'
  | 'date'
  | 'image'
  | 'task'
  | 'poll'

export interface PageMenuItem {
  id: PageMenuItemId
  icon: LucideIcon
  shortcut?: ShortcutTokens
  /** Check mark in the "Stili" menu; absent for pure insertions. */
  isActive?: (editor: Editor) => boolean
  run: (editor: Editor, actions: PageMenuActions) => void
}

function isCollapsibleHeading(editor: Editor): boolean {
  const block = resolveBlock(editor.state)
  return block?.node.type.name === NODE_NAME.heading && block.node.attrs.collapsible === true
}

function blockItem(id: BlockTypeId, icon: LucideIcon, shortcut?: ShortcutTokens): PageMenuItem {
  return {
    id,
    icon,
    ...(shortcut ? { shortcut } : {}),
    // A collapsible heading is its own entry: the plain one is checked only when it is not collapsible.
    isActive: (editor) => getActiveBlockType(editor) === id && !isCollapsibleHeading(editor),
    run: (editor) => {
      editor.commands.setBlockType(id)
      editor.commands.focus()
    },
  }
}

function collapsibleHeadingItem(level: HeadingLevel, icon: LucideIcon): PageMenuItem {
  const id: CollapsibleHeadingId = `collapsibleHeading${level}`
  return {
    id,
    icon,
    isActive: (editor) => getActiveBlockType(editor) === `heading${level}` && isCollapsibleHeading(editor),
    run: (editor) => {
      editor.commands.setBlockType(`heading${level}`)
      editor.chain().focus().updateAttributes(NODE_NAME.heading, { collapsible: true }).run()
    },
  }
}

const ITEMS: readonly PageMenuItem[] = [
  blockItem('paragraph', Pilcrow, ['Mod', 'Alt', '0']),
  {
    id: 'collapsibleParagraph',
    icon: ChevronsDownUp,
    isActive: (editor) => resolveBlock(editor.state)?.node.type.name === NODE_NAME.collapsible,
    run: (editor) => {
      editor.chain().focus().insertCollapsible().run()
    },
  },
  blockItem('heading1', Heading1, ['Mod', 'Alt', '1']),
  blockItem('heading2', Heading2, ['Mod', 'Alt', '2']),
  blockItem('heading3', Heading3, ['Mod', 'Alt', '3']),
  blockItem('heading4', Heading4, ['Mod', 'Alt', '4']),
  collapsibleHeadingItem(1, Heading1),
  collapsibleHeadingItem(2, Heading2),
  collapsibleHeadingItem(3, Heading3),
  collapsibleHeadingItem(4, Heading4),
  blockItem('bulletList', List, ['Mod', 'Shift', '8']),
  blockItem('orderedList', ListOrdered, ['Mod', 'Shift', '7']),
  blockItem('taskList', ListTodo, ['Mod', 'Shift', '9']),
  blockItem('blockquote', Quote),
  blockItem('callout', Info),
  blockItem('codeBlock', FileCode),
  {
    id: 'inlineCode',
    icon: Code,
    isActive: (editor) => editor.isActive('code'),
    run: (editor) => {
      editor.chain().focus().toggleCode().run()
    },
  },
  {
    id: 'table',
    icon: Table,
    run: (editor) => {
      editor.chain().focus().insertTable({ rows: TABLE_DEFAULT_ROWS, cols: TABLE_DEFAULT_COLS, withHeaderRow: true }).run()
    },
  },
  {
    id: 'divider',
    icon: Minus,
    run: (editor) => {
      editor.chain().focus().setHorizontalRule().run()
    },
  },
  {
    id: 'date',
    icon: CalendarDays,
    run: (editor) => {
      editor.chain().focus().insertDate().run()
    },
  },
  { id: 'image', icon: Image, run: (_editor, actions) => actions.pickImage() },
  { id: 'task', icon: SquareCheckBig, run: (_editor, actions) => actions.insertTask() },
  { id: 'poll', icon: ChartBar, run: (_editor, actions) => actions.insertPoll() },
]

const ITEMS_BY_ID = new Map<PageMenuItemId, PageMenuItem>(ITEMS.map((item) => [item.id, item]))

export function getMenuItem(id: PageMenuItemId): PageMenuItem {
  const item = ITEMS_BY_ID.get(id)
  if (!item) {
    throw new Error(`Unknown page menu item: ${id}`)
  }
  return item
}

function pick(ids: readonly PageMenuItemId[]): PageMenuItem[] {
  return ids.map(getMenuItem)
}

/** "Stili" submenu of the selection bar, in display order. */
export const STYLE_MENU_ITEMS = pick([
  'paragraph',
  'collapsibleParagraph',
  'heading1',
  'heading2',
  'heading3',
  'heading4',
  'collapsibleHeading1',
  'collapsibleHeading2',
  'collapsibleHeading3',
  'collapsibleHeading4',
  'bulletList',
  'orderedList',
  'taskList',
  'blockquote',
  'callout',
  'inlineCode',
])

/** The "/" menu: every block style plus the insertions (inline code is a mark, not a block). */
export const SLASH_MENU_ITEMS = pick([
  'paragraph',
  'collapsibleParagraph',
  'heading1',
  'heading2',
  'heading3',
  'heading4',
  'collapsibleHeading1',
  'collapsibleHeading2',
  'collapsibleHeading3',
  'collapsibleHeading4',
  'bulletList',
  'orderedList',
  'taskList',
  'blockquote',
  'callout',
  'codeBlock',
  'table',
  'divider',
  'date',
  'image',
  'task',
  'poll',
])

/** Buttons shown directly on the bottom bar. */
export const BOTTOM_BAR_PRIMARY_ITEMS = pick(['table', 'bulletList', 'taskList', 'task', 'poll'])

const BOTTOM_BAR_PRIMARY_IDS: ReadonlySet<PageMenuItemId> = new Set(BOTTOM_BAR_PRIMARY_ITEMS.map((item) => item.id))

/** The "..." dropdown of the bottom bar: everything not already a button. */
export const BOTTOM_BAR_MORE_ITEMS = SLASH_MENU_ITEMS.filter((item) => !BOTTOM_BAR_PRIMARY_IDS.has(item.id))
