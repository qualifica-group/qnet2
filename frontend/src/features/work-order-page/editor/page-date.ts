import { Node, mergeAttributes } from '@tiptap/core'
import { formatDate } from '@/lib/formatting/date-display'
import { BLOCK_DATA_TYPE, NODE_NAME } from '@/features/work-order-page/editor/constants'

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    pageDate: {
      /** Inserts an inline date badge; `date` is `YYYY-MM-DD`, default today. */
      insertDate: (date?: string) => ReturnType
    }
  }
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

export function todayIso(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

/** Display text of a badge: the date in the user's own display format preference. */
export function formatPageDate(iso: string): string {
  return formatDate(iso) || iso
}

/** Inline date badge: `time[data-type="date"][datetime=YYYY-MM-DD]`. */
export const PageDate = Node.create({
  name: NODE_NAME.date,
  group: 'inline',
  inline: true,
  atom: true,
  addAttributes() {
    return {
      date: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('datetime'),
        // Written explicitly as `datetime` in renderHTML below.
        renderHTML: () => ({}),
      },
    }
  },
  parseHTML() {
    return [
      {
        tag: `time[data-type="${BLOCK_DATA_TYPE.date}"][datetime]`,
        getAttrs: (element) => (ISO_DATE.test((element as HTMLElement).getAttribute('datetime') ?? '') ? null : false),
      },
    ]
  },
  renderHTML({ node, HTMLAttributes }) {
    const iso = String(node.attrs.date ?? '')
    return [
      'time',
      mergeAttributes(HTMLAttributes, { 'data-type': BLOCK_DATA_TYPE.date, datetime: iso }),
      formatPageDate(iso),
    ]
  },
  renderText({ node }) {
    return String(node.attrs.date ?? '')
  },
  addCommands() {
    return {
      insertDate:
        (date) =>
        ({ commands }) =>
          commands.insertContent({ type: this.name, attrs: { date: date ?? todayIso() } }),
    }
  },
})
