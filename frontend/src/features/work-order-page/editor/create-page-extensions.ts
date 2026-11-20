import { TaskItem, TaskList } from '@tiptap/extension-list'
import { Placeholder } from '@tiptap/extensions'
import { StarterKit } from '@tiptap/starter-kit'
import type { Extensions } from '@tiptap/core'
import { safeUrl } from '@/lib/safe-url'
import { RICH_TEXT_ALLOWED_LINK_PROTOCOLS } from '@/components/rich-text/rich-text-constants'
import { RichTextImage } from '@/components/rich-text/rich-text-extensions'
import { PollExtension } from '@/features/work-order-page/blocks/poll-extension'
import { TaskRefExtension } from '@/features/work-order-page/blocks/task-ref-extension'
import { PageBlockCommands } from '@/features/work-order-page/editor/block-commands'
import { Callout } from '@/features/work-order-page/editor/callout'
import { CollapseStateExtension, type CollapseLabels } from '@/features/work-order-page/editor/collapse-state'
import { CollapsibleBlock, CollapsibleContent, CollapsibleSummary } from '@/features/work-order-page/editor/collapsible-block'
import { CollapsibleHeading } from '@/features/work-order-page/editor/collapsible-heading'
import { HEADING_LEVELS } from '@/features/work-order-page/editor/constants'
import { Highlight } from '@/features/work-order-page/editor/highlight'
import { PageDate } from '@/features/work-order-page/editor/page-date'
import { Table } from '@/features/work-order-page/editor/table'
import { TableCell, TableHeader, TableRow } from '@/features/work-order-page/editor/table-parts'
import { TextColorMark } from '@/features/work-order-page/editor/text-color'

export interface CreatePageExtensionsOptions {
  placeholder?: string
  collapseLabels?: CollapseLabels
}

/**
 * Full extension set of the workspace page: the schema matches exactly the
 * server `page` HTML profile (spec 0380), so whatever the editor emits
 * survives the sanitizer. Used by both the editable page and its read-only view.
 */
export function createPageExtensions({ placeholder, collapseLabels }: CreatePageExtensionsOptions = {}): Extensions {
  return [
    StarterKit.configure({
      heading: { levels: [...HEADING_LEVELS] },
      link: {
        openOnClick: false,
        HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
        isAllowedUri: (url) => safeUrl(url, RICH_TEXT_ALLOWED_LINK_PROTOCOLS) !== undefined,
      },
    }),
    RichTextImage,
    TaskList,
    TaskItem.configure({ nested: true }),
    Highlight,
    TextColorMark,
    PageDate,
    Callout,
    CollapsibleBlock,
    CollapsibleSummary,
    CollapsibleContent,
    CollapsibleHeading,
    CollapseStateExtension.configure(collapseLabels ? { labels: collapseLabels } : {}),
    Table,
    TableRow,
    TableHeader,
    TableCell,
    TaskRefExtension,
    PollExtension,
    PageBlockCommands,
    ...(placeholder ? [Placeholder.configure({ placeholder })] : []),
  ]
}
