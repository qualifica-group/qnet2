/**
 * Single source of truth of the page editor vocabulary (spec 0380 html_profile):
 * the palettes and enums below mirror the server-side `page` sanitizer profile,
 * which drops any value outside them. Menus import these lists instead of
 * redeclaring them.
 */

export const HIGHLIGHT_COLORS = ['yellow', 'green', 'blue', 'pink', 'purple'] as const
export type HighlightColor = (typeof HIGHLIGHT_COLORS)[number]

export const TEXT_COLORS = ['gray', 'red', 'orange', 'green', 'blue', 'purple'] as const
export type TextColor = (typeof TEXT_COLORS)[number]

export const CALLOUT_VARIANTS = ['info', 'success', 'warning', 'error'] as const
export type CalloutVariant = (typeof CALLOUT_VARIANTS)[number]

export const HEADING_LEVELS = [1, 2, 3, 4] as const
export type HeadingLevel = (typeof HEADING_LEVELS)[number]

/** `data-type` values of the custom blocks, as written in the persisted HTML. */
export const BLOCK_DATA_TYPE = {
  callout: 'callout',
  collapsible: 'collapsible',
  collapsibleSummary: 'collapsible-summary',
  collapsibleContent: 'collapsible-content',
  date: 'date',
  taskRef: 'task-ref',
  poll: 'poll',
} as const

/** Node/mark names registered in the ProseMirror schema. */
export const NODE_NAME = {
  heading: 'heading',
  table: 'table',
  tableRow: 'tableRow',
  tableHeader: 'tableHeader',
  tableCell: 'tableCell',
  callout: 'callout',
  collapsible: 'collapsible',
  collapsibleSummary: 'collapsibleSummary',
  collapsibleContent: 'collapsibleContent',
  date: 'pageDate',
  taskRef: 'taskRef',
  poll: 'poll',
} as const

export const MARK_NAME = {
  highlight: 'highlight',
  textColor: 'textColor',
} as const

/** "Trasforma in" targets of the block menu and the slash menu. */
export const BLOCK_TYPES = [
  'paragraph',
  'heading1',
  'heading2',
  'heading3',
  'heading4',
  'bulletList',
  'orderedList',
  'taskList',
  'blockquote',
  'codeBlock',
  'callout',
] as const
export type BlockTypeId = (typeof BLOCK_TYPES)[number]

export const TABLE_DEFAULT_ROWS = 3
export const TABLE_DEFAULT_COLS = 3
