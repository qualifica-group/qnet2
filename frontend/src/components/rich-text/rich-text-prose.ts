import { cn } from '@/lib/utils'

/**
 * Minimal typography for the D-1 tag set, shared by `RichTextEditor` and
 * `RichTextContent` so an edited field and its read-only display never look
 * different. Tailwind arbitrary child selectors, tokens only (no hard-coded
 * colors, per ui-design.md §1-bis).
 */
export const RICH_TEXT_PROSE_CLASS = cn(
  '[&_p]:my-1',
  '[&_h2]:mt-3 [&_h2]:mb-1 [&_h2]:text-base [&_h2]:font-semibold',
  '[&_h3]:mt-2 [&_h3]:mb-1 [&_h3]:text-sm [&_h3]:font-semibold',
  '[&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-0.5',
  '[&_blockquote]:my-1 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground',
  '[&_pre]:my-1 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-2 [&_pre]:text-xs [&_code]:font-mono [&_code]:text-xs',
  '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2',
  '[&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-md',
  // Mention highlight (D-7): the node itself carries no class/style — the
  // sanitizer allow-list only keeps `data-type`/`data-id`/`data-label` on it
  // (D-1) — so the tint is applied from here, by attribute selector, the
  // same way `features/notes/mention-badge.tsx` tints a resolved mention
  // chip (`bg-*/text-*` pair, never a hard-coded color).
  '[&_[data-type="mention"]]:rounded-full [&_[data-type="mention"]]:bg-accent',
  '[&_[data-type="mention"]]:px-1.5 [&_[data-type="mention"]]:py-0.5',
  '[&_[data-type="mention"]]:text-accent-foreground [&_[data-type="mention"]]:font-medium',
)

/** Renders the `Placeholder` extension's `data-placeholder` pseudo-content. */
export const RICH_TEXT_PLACEHOLDER_CLASS = cn(
  '[&_.is-editor-empty_p.is-empty:first-child]:before:pointer-events-none',
  '[&_.is-editor-empty_p.is-empty:first-child]:before:float-left',
  '[&_.is-editor-empty_p.is-empty:first-child]:before:h-0',
  '[&_.is-editor-empty_p.is-empty:first-child]:before:text-muted-foreground',
  '[&_.is-editor-empty_p.is-empty:first-child]:before:content-[attr(data-placeholder)]',
)
