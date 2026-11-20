import { cn } from '@/lib/utils'
import { RICH_TEXT_PROSE_CLASS } from '@/components/rich-text/rich-text-prose'

/**
 * Typography of the page profile on top of the shared rich text prose. The
 * persisted HTML has no `class`/`style`, so every look is applied here by
 * attribute selector. Class names are written out in full (Tailwind scans
 * source text; it cannot see concatenated names).
 */
export const PAGE_PROSE_CLASS = cn(
  RICH_TEXT_PROSE_CLASS,
  '[&_h1]:mt-4 [&_h1]:mb-1 [&_h1]:text-xl [&_h1]:font-semibold',
  '[&_h4]:mt-2 [&_h4]:mb-1 [&_h4]:text-sm [&_h4]:font-medium',
  '[&_hr]:my-3 [&_hr]:border-border',
  // Task list: checkbox + content on one row.
  '[&_ul[data-type=taskList]]:list-none [&_ul[data-type=taskList]]:pl-1',
  '[&_li[data-type=taskItem]]:flex [&_li[data-type=taskItem]]:items-start [&_li[data-type=taskItem]]:gap-2',
  '[&_li[data-type=taskItem]>label]:mt-0.5 [&_li[data-type=taskItem]>div]:min-w-0 [&_li[data-type=taskItem]>div]:flex-1',
  '[&_li[data-type=taskItem][data-checked=true]>div]:text-muted-foreground [&_li[data-type=taskItem][data-checked=true]>div]:line-through',
  // Table scrolls inside itself (AC-023): no horizontal page scroll at 375px.
  '[&_table]:my-2 [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto [&_table]:border-collapse',
  '[&_th]:min-w-24 [&_th]:border [&_th]:border-border [&_th]:bg-surface [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_th]:font-semibold',
  '[&_td]:min-w-24 [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1 [&_td]:align-top',
  '[&_.selectedCell]:bg-accent/40',
  // Callout variants on theme tokens.
  '[&_div[data-type=callout]]:my-2 [&_div[data-type=callout]]:rounded-md [&_div[data-type=callout]]:border-l-4 [&_div[data-type=callout]]:px-3 [&_div[data-type=callout]]:py-1',
  "[&_div[data-type=callout][data-variant=info]]:border-primary [&_div[data-type=callout][data-variant=info]]:bg-primary/10",
  '[&_div[data-type=callout][data-variant=success]]:border-success [&_div[data-type=callout][data-variant=success]]:bg-success/10',
  '[&_div[data-type=callout][data-variant=warning]]:border-warning [&_div[data-type=callout][data-variant=warning]]:bg-warning/10',
  '[&_div[data-type=callout][data-variant=error]]:border-destructive [&_div[data-type=callout][data-variant=error]]:bg-destructive/10',
  // Collapsible block.
  '[&_div[data-type=collapsible]]:my-2 [&_div[data-type=collapsible]]:rounded-md [&_div[data-type=collapsible]]:border [&_div[data-type=collapsible]]:border-border',
  '[&_div[data-type=collapsible-summary]]:relative [&_div[data-type=collapsible-summary]]:bg-surface [&_div[data-type=collapsible-summary]]:px-2 [&_div[data-type=collapsible-summary]]:py-1 [&_div[data-type=collapsible-summary]]:pl-7 [&_div[data-type=collapsible-summary]]:font-medium',
  '[&_div[data-type=collapsible-content]]:px-3 [&_div[data-type=collapsible-content]]:py-1',
  // Collapse toggle (CSS chevron) and hidden sections: local view state only.
  '[&_.page-collapse-toggle]:absolute [&_.page-collapse-toggle]:left-1.5 [&_.page-collapse-toggle]:ml-0 [&_.page-collapse-toggle]:inline-flex [&_.page-collapse-toggle]:size-4 [&_.page-collapse-toggle]:items-center [&_.page-collapse-toggle]:justify-center [&_.page-collapse-toggle]:text-muted-foreground',
  "[&_.page-collapse-toggle]:before:content-[''] [&_.page-collapse-toggle]:before:size-1.5 [&_.page-collapse-toggle]:before:rotate-45 [&_.page-collapse-toggle]:before:border-b-2 [&_.page-collapse-toggle]:before:border-r-2 [&_.page-collapse-toggle]:before:border-current [&_.page-collapse-toggle]:before:transition-transform",
  '[&_.is-collapsed_.page-collapse-toggle]:before:-rotate-45',
  '[&_h1]:relative [&_h2]:relative [&_h3]:relative [&_h4]:relative [&_h1[data-collapsible=true]]:pl-6 [&_h2[data-collapsible=true]]:pl-6 [&_h3[data-collapsible=true]]:pl-6 [&_h4[data-collapsible=true]]:pl-6',
  '[&_.page-collapsed-hidden]:hidden',
  // Inline date badge.
  '[&_time[data-type=date]]:rounded-md [&_time[data-type=date]]:bg-accent [&_time[data-type=date]]:px-1.5 [&_time[data-type=date]]:py-0.5 [&_time[data-type=date]]:text-xs [&_time[data-type=date]]:font-medium [&_time[data-type=date]]:text-accent-foreground',
  // Highlight and text colors (fixed palettes, translucent so they work in dark mode).
  '[&_mark]:rounded-sm [&_mark]:text-inherit',
  '[&_mark[data-color=yellow]]:bg-yellow-400/40 [&_mark[data-color=green]]:bg-green-500/30 [&_mark[data-color=blue]]:bg-blue-500/30 [&_mark[data-color=pink]]:bg-pink-500/30 [&_mark[data-color=purple]]:bg-purple-500/30',
  '[&_span[data-color=gray]]:text-muted-foreground [&_span[data-color=red]]:text-red-600 dark:[&_span[data-color=red]]:text-red-400',
  '[&_span[data-color=orange]]:text-orange-600 dark:[&_span[data-color=orange]]:text-orange-400',
  '[&_span[data-color=green]]:text-green-700 dark:[&_span[data-color=green]]:text-green-400',
  '[&_span[data-color=blue]]:text-blue-600 dark:[&_span[data-color=blue]]:text-blue-400',
  '[&_span[data-color=purple]]:text-purple-600 dark:[&_span[data-color=purple]]:text-purple-400',
  // Selected atom blocks (task/poll).
  '[&_.ProseMirror-selectednode]:outline-2 [&_.ProseMirror-selectednode]:outline-ring',
)
